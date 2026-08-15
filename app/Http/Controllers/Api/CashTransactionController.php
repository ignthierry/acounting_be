<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Services\JournalService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashTransactionController extends Controller
{
    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * List cash transactions & expenses
     */
    public function index(Request $request)
    {
        $query = CashTransaction::with(['account', 'contraAccount']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('account_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('account_id', $request->account_id)
                  ->orWhere('contra_account_id', $request->account_id);
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                  ->orWhere('recipient_vendor', 'LIKE', "%{$search}%")
                  ->orWhere('reference_number', 'LIKE', "%{$search}%");
            });
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($transactions);
    }

    /**
     * Store new cash transaction (Uang Masuk / Uang Keluar / Biaya Operasional / Pembiayaan)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'contra_account_id' => 'required|exists:accounts,id|different:account_id',
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'recipient_vendor' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'required|string',
            'reference_number' => 'nullable|string|max:100',
            'status' => 'nullable|in:Lunas,Terjadwal',
        ]);

        $companyId = $request->user()->company_id;
        $amount = round((float)$validated['amount'], 2);

        return DB::transaction(function () use ($companyId, $validated, $amount) {
            // Prepare journal lines based on transaction type
            $journalLines = [];

            if ($validated['type'] === 'in') {
                // Uang Masuk: Debit Kas/Bank, Kredit Akun Lawan (Pendapatan/Modal/dll)
                $journalLines[] = [
                    'account_id' => $validated['account_id'],
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Penerimaan kas: ' . $validated['notes'],
                ];
                $journalLines[] = [
                    'account_id' => $validated['contra_account_id'],
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Sumber penerimaan: ' . $validated['notes'],
                ];
            } else {
                // Uang Keluar / Beban: Debit Akun Lawan (Beban/Utang), Kredit Kas/Bank
                $journalLines[] = [
                    'account_id' => $validated['contra_account_id'],
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Alokasi pengeluaran: ' . $validated['notes'],
                ];
                $journalLines[] = [
                    'account_id' => $validated['account_id'],
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Pengeluaran kas: ' . $validated['notes'],
                ];
            }

            // Create automatic journal entry
            $journalEntry = $this->journalService->createEntry(
                $companyId,
                $validated['transaction_date'],
                $validated['notes'],
                $journalLines,
                $validated['type'] === 'in' ? 'cash' : 'expense',
                $validated['reference_number'] ?? null
            );

            // Record cash transaction
            $transaction = CashTransaction::create([
                'company_id' => $companyId,
                'account_id' => $validated['account_id'],
                'contra_account_id' => $validated['contra_account_id'],
                'type' => $validated['type'],
                'amount' => $amount,
                'transaction_date' => $validated['transaction_date'],
                'recipient_vendor' => $validated['recipient_vendor'] ?? null,
                'category' => $validated['category'] ?? null,
                'payment_method' => $validated['payment_method'] ?? 'Cash',
                'notes' => $validated['notes'],
                'reference_number' => $validated['reference_number'] ?? null,
                'status' => $validated['status'] ?? 'Lunas',
                'journal_entry_id' => $journalEntry->id,
            ]);

            return response()->json([
                'message' => 'Transaksi kas dan entri pembukuan berhasil disimpan.',
                'data' => $transaction->load(['account', 'contraAccount', 'journalEntry']),
            ], 201);
        });
    }

    /**
     * Transfer funds between Cash and Bank accounts
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $companyId = $request->user()->company_id;
        $amount = round((float)$validated['amount'], 2);
        $notes = $validated['notes'] ?: 'Transfer dana antar rekening kas/bank';

        return DB::transaction(function () use ($companyId, $validated, $amount, $notes) {
            $fromAccount = Account::find($validated['from_account_id']);
            $toAccount = Account::find($validated['to_account_id']);

            // Journal for Transfer: Debit ToAccount, Credit FromAccount
            $journalLines = [
                [
                    'account_id' => $toAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => "Terima transfer dari {$fromAccount->name}",
                ],
                [
                    'account_id' => $fromAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => "Transfer keluar ke {$toAccount->name}",
                ],
            ];

            $journalEntry = $this->journalService->createEntry(
                $companyId,
                $validated['transaction_date'],
                $notes,
                $journalLines,
                'cash',
                $validated['reference_number'] ?? null
            );

            $transaction = CashTransaction::create([
                'company_id' => $companyId,
                'account_id' => $fromAccount->id,
                'contra_account_id' => $toAccount->id,
                'type' => 'transfer',
                'amount' => $amount,
                'transaction_date' => $validated['transaction_date'],
                'notes' => $notes,
                'reference_number' => $validated['reference_number'] ?? null,
                'status' => 'Lunas',
                'journal_entry_id' => $journalEntry->id,
            ]);

            return response()->json([
                'message' => 'Transfer dana antar akun kas/bank berhasil diselesaikan.',
                'data' => $transaction->load(['account', 'contraAccount', 'journalEntry']),
            ], 201);
        });
    }
}
