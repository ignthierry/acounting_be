<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * List all journal entries with lines
     */
    public function index(Request $request)
    {
        $query = JournalEntry::with(['lines.account']);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('entry_date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('reference', 'LIKE', "%{$search}%");
            });
        }

        $entries = $query->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($entries);
    }

    /**
     * Store new manual double-entry journal entry
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'description' => 'required|string',
            'reference' => 'nullable|string|max:100',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'required_without:lines.*.credit|numeric|min:0',
            'lines.*.credit' => 'required_without:lines.*.debit|numeric|min:0',
            'lines.*.memo' => 'nullable|string|max:255',
        ]);

        try {
            $entry = $this->journalService->createEntry(
                $request->user()->company_id,
                $validated['entry_date'],
                $validated['description'],
                $validated['lines'],
                'manual',
                $validated['reference'] ?? null
            );

            return response()->json([
                'message' => 'Entri jurnal umum berhasil disimpan dan terintegrasi ke buku besar.',
                'data' => $entry->load('lines.account'),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show single journal entry
     */
    public function show(JournalEntry $journal)
    {
        return response()->json([
            'data' => $journal->load('lines.account'),
        ]);
    }

    /**
     * Update a manual journal entry (replace lines)
     */
    public function update(Request $request, JournalEntry $journal)
    {
        // Only manual entries may be edited. System-generated entries
        // (invoice/payment/stock/expense) are managed by their source modules.
        if ($journal->source !== 'manual') {
            return response()->json([
                'message' => 'Entri jurnal dari sistem (' . $journal->source . ') tidak dapat diubah langsung. Ubah melalui dokumen sumbernya.',
            ], 422);
        }

        $validated = $request->validate([
            'entry_date' => 'required|date',
            'description' => 'required|string',
            'reference' => 'nullable|string|max:100',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'required_without:lines.*.credit|numeric|min:0',
            'lines.*.credit' => 'required_without:lines.*.debit|numeric|min:0',
            'lines.*.memo' => 'nullable|string|max:255',
        ]);

        try {
            // Recalculate totals & validate balance
            $totalDebit = 0.00;
            $totalCredit = 0.00;
            foreach ($validated['lines'] as $line) {
                $totalDebit += round((float)($line['debit'] ?? 0), 2);
                $totalCredit += round((float)($line['credit'] ?? 0), 2);
            }
            $totalDebit = round($totalDebit, 2);
            $totalCredit = round($totalCredit, 2);

            if (bccomp((string)$totalDebit, (string)$totalCredit, 2) !== 0) {
                throw new Exception("Entri jurnal tidak balance! Total Debit (Rp " . number_format($totalDebit, 2) . ") != Total Kredit (Rp " . number_format($totalCredit, 2) . ")");
            }
            if ($totalDebit <= 0) {
                throw new Exception("Nominal transaksi jurnal harus lebih besar dari 0!");
            }

            $updated = DB::transaction(function () use ($journal, $validated, $totalDebit, $totalCredit) {
                $journal->update([
                    'entry_date' => $validated['entry_date'],
                    'description' => $validated['description'],
                    'reference' => $validated['reference'] ?? null,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                ]);

                // Replace lines
                $journal->lines()->delete();
                foreach ($validated['lines'] as $line) {
                    $journal->lines()->create([
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                        'memo' => $line['memo'] ?? null,
                    ]);
                }

                return $journal;
            });

            return response()->json([
                'message' => 'Entri jurnal umum berhasil diperbarui.',
                'data' => $updated->load('lines.account'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a manual journal entry
     */
    public function destroy(JournalEntry $journal)
    {
        if ($journal->source !== 'manual') {
            return response()->json([
                'message' => 'Entri jurnal dari sistem (' . $journal->source . ') tidak dapat dihapus. Hubungkan melalui dokumen sumbernya.',
            ], 422);
        }

        $journal->delete(); // journal_lines cascade; dokumen terkait (invoice/payment/cash) → set null

        return response()->json([
            'message' => 'Entri jurnal umum berhasil dihapus.',
        ]);
    }
}
