<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Exception;
use Illuminate\Http\Request;

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
}
