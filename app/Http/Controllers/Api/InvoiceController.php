<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\JournalService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * List invoices with status, customer, and date filter
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['customer', 'items', 'payments']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Auto update overdue status for unpaid invoices past due date
        $today = date('Y-m-d');
        Invoice::where('status', 'sent')
            ->where('due_date', '<', $today)
            ->update(['status' => 'overdue']);

        $invoices = $query->orderBy('issue_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($invoices);
    }

    /**
     * Create new invoice with line items
     */
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'status' => 'nullable|in:draft,sent',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($companyId, $validated) {
            // Generate invoice number
            $yearMonth = date('Ym', strtotime($validated['issue_date']));
            $count = Invoice::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('invoice_number', 'LIKE', "INV-{$yearMonth}-%")
                ->count() + 1;
            $invoiceNumber = sprintf("INV-%s-%04d", $yearMonth, $count);

            $subtotal = 0.00;
            $totalCogs = 0.00;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = (float)$item['unit_price'];
                $lineSubtotal = round($qty * $price, 2);
                $subtotal += $lineSubtotal;

                $itemsData[] = [
                    'product_id' => $item['product_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $lineSubtotal,
                ];

                // Handle product inventory decrease & HPP
                if (!empty($item['product_id'])) {
                    $product = Product::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->lockForUpdate()
                        ->find($item['product_id']);

                    if ($product) {
                        $productCost = (float)$product->cost_price;
                        $totalCogs += round($qty * $productCost, 2);

                        $product->stock_quantity -= $qty;
                        $product->save();

                        // Record stock movement
                        StockMovement::create([
                            'company_id' => $companyId,
                            'product_id' => $product->id,
                            'type' => 'sale',
                            'quantity' => $qty,
                            'unit_cost' => $productCost,
                            'total_cost' => round($qty * $productCost, 2),
                            'movement_date' => $validated['issue_date'],
                            'reference' => $invoiceNumber,
                        ]);
                    }
                }
            }

            $tax = round((float)($validated['tax'] ?? 0), 2);
            $totalAmount = $subtotal + $tax;
            $status = $validated['status'] ?? 'sent';

            // Find COA accounts for invoice
            $piutangAccount = Account::where('code', '120-10')->first() ?: Account::where('category', 'Aset Lancar')->first();
            $salesAccount = Account::where('code', '400-10')->first() ?: Account::where('group', 'Pendapatan')->first();
            $hppAccount = Account::where('code', '500-10')->first();
            $inventoryAccount = Account::where('code', '140-10')->first();

            // Journal for Invoice: Debit Piutang Usaha, Credit Pendapatan Penjualan
            $journalLines = [
                [
                    'account_id' => $piutangAccount->id,
                    'debit' => $totalAmount,
                    'credit' => 0,
                    'memo' => "Tagihan piutang invoice {$invoiceNumber}",
                ],
                [
                    'account_id' => $salesAccount->id,
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'memo' => "Pendapatan penjualan invoice {$invoiceNumber}",
                ],
            ];

            // If inventory products were sold, also record COGS / Inventory journal
            if ($totalCogs > 0 && $hppAccount && $inventoryAccount) {
                $journalLines[] = [
                    'account_id' => $hppAccount->id,
                    'debit' => $totalCogs,
                    'credit' => 0,
                    'memo' => "HPP penjualan invoice {$invoiceNumber}",
                ];
                $journalLines[] = [
                    'account_id' => $inventoryAccount->id,
                    'debit' => 0,
                    'credit' => $totalCogs,
                    'memo' => "Pengurangan persediaan invoice {$invoiceNumber}",
                ];
            }

            $journalEntry = $this->journalService->createEntry(
                $companyId,
                $validated['issue_date'],
                "Penerbitan Invoice {$invoiceNumber}",
                $journalLines,
                'invoice',
                $invoiceNumber
            );

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'customer_id' => $validated['customer_id'],
                'invoice_number' => $invoiceNumber,
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'status' => $status,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total_amount' => $totalAmount,
                'paid_amount' => 0.00,
                'notes' => $validated['notes'] ?? null,
                'journal_entry_id' => $journalEntry->id,
            ]);

            foreach ($itemsData as $item) {
                $invoice->items()->create($item);
            }

            return response()->json([
                'message' => 'Invoice berhasil dibuat dan jurnal piutang telah tercatat.',
                'data' => $invoice->load(['customer', 'items', 'journalEntry']),
            ], 201);
        });
    }

    /**
     * Show single invoice
     */
    public function show(Invoice $invoice)
    {
        return response()->json([
            'data' => $invoice->load(['customer', 'items.product', 'payments.account', 'journalEntry.lines.account']),
        ]);
    }

    /**
     * Record payment on an invoice
     */
    public function addPayment(Request $request, Invoice $invoice)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id', // Kas / Bank account
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $amount = round((float)$validated['amount'], 2);
        $remaining = $invoice->total_amount - $invoice->paid_amount;

        if ($amount > $remaining) {
            return response()->json([
                'message' => "Nominal pembayaran (Rp " . number_format($amount, 2) . ") melebihi sisa tagihan (Rp " . number_format($remaining, 2) . ")",
            ], 422);
        }

        return DB::transaction(function () use ($companyId, $invoice, $validated, $amount) {
            $yearMonth = date('Ym', strtotime($validated['payment_date']));
            $count = Payment::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->count() + 1;
            $paymentNumber = sprintf("PAY-%s-%04d", $yearMonth, $count);

            $piutangAccount = Account::where('code', '120-10')->first() ?: Account::where('category', 'Aset Lancar')->first();
            $destinationAccount = Account::find($validated['account_id']);

            // Journal for Payment: Debit Kas/Bank, Credit Piutang Usaha
            $journalLines = [
                [
                    'account_id' => $destinationAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => "Penerimaan pembayaran invoice {$invoice->invoice_number}",
                ],
                [
                    'account_id' => $piutangAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => "Pelunasan piutang invoice {$invoice->invoice_number}",
                ],
            ];

            $journalEntry = $this->journalService->createEntry(
                $companyId,
                $validated['payment_date'],
                "Penerimaan Pembayaran {$invoice->invoice_number}",
                $journalLines,
                'payment',
                $paymentNumber
            );

            $payment = Payment::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'account_id' => $destinationAccount->id,
                'payment_number' => $paymentNumber,
                'amount' => $amount,
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'] ?? 'Transfer',
                'notes' => $validated['notes'] ?? null,
                'journal_entry_id' => $journalEntry->id,
            ]);

            // Update invoice paid amount & status
            $invoice->paid_amount += $amount;
            if (bccomp((string)$invoice->paid_amount, (string)$invoice->total_amount, 2) >= 0) {
                $invoice->status = 'paid';
            }
            $invoice->save();

            return response()->json([
                'message' => 'Pembayaran berhasil dicatat dan piutang telah berkurang.',
                'data' => $payment->load(['account', 'journalEntry']),
                'invoice' => $invoice->load(['payments', 'customer']),
            ], 201);
        });
    }

    /**
     * Receivables Aging Report (Laporan Umur Piutang)
     */
    public function agingReport(Request $request)
    {
        $companyId = $request->user()->company_id;
        $today = date('Y-m-d');

        $invoices = Invoice::with('customer')
            ->where('company_id', $companyId)
            ->whereIn('status', ['sent', 'overdue'])
            ->get();

        $current = 0.00;     // 0 - 30 days
        $days30to60 = 0.00;  // 31 - 60 days
        $days60to90 = 0.00;  // 61 - 90 days
        $over90 = 0.00;      // > 90 days

        $reportItems = [];

        foreach ($invoices as $inv) {
            $unpaid = $inv->total_amount - $inv->paid_amount;
            $diffDays = (int)((strtotime($today) - strtotime($inv->due_date)) / 86400);

            if ($diffDays <= 0) {
                $bucket = 'Belum Jatuh Tempo';
                $current += $unpaid;
            } elseif ($diffDays <= 30) {
                $bucket = '1 - 30 Hari';
                $current += $unpaid;
            } elseif ($diffDays <= 60) {
                $bucket = '31 - 60 Hari';
                $days30to60 += $unpaid;
            } elseif ($diffDays <= 90) {
                $bucket = '61 - 90 Hari';
                $days60to90 += $unpaid;
            } else {
                $bucket = '> 90 Hari (Kritis)';
                $over90 += $unpaid;
            }

            $reportItems[] = [
                'invoice_number' => $inv->invoice_number,
                'customer' => $inv->customer->name,
                'due_date' => $inv->due_date,
                'overdue_days' => max(0, $diffDays),
                'total_amount' => $inv->total_amount,
                'unpaid_amount' => $unpaid,
                'aging_bucket' => $bucket,
            ];
        }

        return response()->json([
            'summary' => [
                'total_receivables' => $current + $days30to60 + $days60to90 + $over90,
                'current' => $current,
                'days_31_60' => $days30to60,
                'days_61_90' => $days60to90,
                'over_90_days' => $over90,
            ],
            'items' => $reportItems,
        ]);
    }
}
