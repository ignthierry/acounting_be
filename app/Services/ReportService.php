<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Generate Income Statement (Laporan Laba Rugi)
     */
    public function getIncomeStatement(int $companyId, ?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?: date('Y-m-01');
        $endDate = $endDate ?: date('Y-m-t');

        // Query journal lines grouped by account and category
        $lines = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->select(
                'accounts.id as account_id',
                'accounts.code',
                'accounts.name',
                'accounts.category',
                'accounts.group',
                'accounts.normal_balance',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.category', 'accounts.group', 'accounts.normal_balance')
            ->get();

        $revenues = [];
        $totalRevenue = 0.00;

        $cogs = [];
        $totalCogs = 0.00;

        $operatingExpenses = [];
        $totalOperatingExpense = 0.00;

        $otherExpenses = [];
        $totalOtherExpense = 0.00;

        foreach ($lines as $line) {
            $debit = (float)$line->total_debit;
            $credit = (float)$line->total_credit;

            if ($line->group === 'Pendapatan') {
                $amount = $credit - $debit;
                $revenues[] = [
                    'code' => $line->code,
                    'name' => $line->name,
                    'amount' => $amount,
                ];
                $totalRevenue += $amount;
            } elseif ($line->category === 'Harga Pokok') {
                $amount = $debit - $credit;
                $cogs[] = [
                    'code' => $line->code,
                    'name' => $line->name,
                    'amount' => $amount,
                ];
                $totalCogs += $amount;
            } elseif ($line->category === 'Beban Operasional') {
                $amount = $debit - $credit;
                $operatingExpenses[] = [
                    'code' => $line->code,
                    'name' => $line->name,
                    'amount' => $amount,
                ];
                $totalOperatingExpense += $amount;
            } elseif ($line->group === 'Beban') {
                $amount = $debit - $credit;
                $otherExpenses[] = [
                    'code' => $line->code,
                    'name' => $line->name,
                    'amount' => $amount,
                ];
                $totalOtherExpense += $amount;
            }
        }

        $grossProfit = $totalRevenue - $totalCogs;
        $totalExpenses = $totalOperatingExpense + $totalOtherExpense;
        $netIncome = $grossProfit - $totalExpenses;

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'revenues' => [
                'items' => $revenues,
                'total' => $totalRevenue,
            ],
            'cogs' => [
                'items' => $cogs,
                'total' => $totalCogs,
            ],
            'gross_profit' => $grossProfit,
            'operating_expenses' => [
                'items' => $operatingExpenses,
                'total' => $totalOperatingExpense,
            ],
            'other_expenses' => [
                'items' => $otherExpenses,
                'total' => $totalOtherExpense,
            ],
            'total_expenses' => $totalExpenses,
            'net_income' => $netIncome,
        ];
    }

    /**
     * Generate Balance Sheet (Neraca / Posisi Keuangan)
     */
    public function getBalanceSheet(int $companyId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: date('Y-m-d');

        // Calculate account balances up to the specified date from JournalLines
        $accountBalances = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.entry_date', '<=', $asOfDate)
            ->select(
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.category',
                'accounts.group',
                'accounts.normal_balance',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.category', 'accounts.group', 'accounts.normal_balance')
            ->get();

        $currentAssets = [];
        $totalCurrentAssets = 0.00;

        $fixedAssets = [];
        $totalFixedAssets = 0.00;

        $liabilities = [];
        $totalLiabilities = 0.00;

        $equities = [];
        $totalEquity = 0.00;

        $currentYearNetIncome = 0.00;

        foreach ($accountBalances as $acc) {
            $debit = (float)$acc->total_debit;
            $credit = (float)$acc->total_credit;

            if ($acc->group === 'Aset') {
                $balance = ($acc->normal_balance === 'Debit') ? ($debit - $credit) : ($credit - $debit);
                if ($acc->category === 'Aset Lancar') {
                    $currentAssets[] = [
                        'code' => $acc->code,
                        'name' => $acc->name,
                        'balance' => $balance,
                    ];
                    $totalCurrentAssets += $balance;
                } else {
                    $fixedAssets[] = [
                        'code' => $acc->code,
                        'name' => $acc->name,
                        'balance' => $balance,
                    ];
                    $totalFixedAssets += $balance;
                }
            } elseif ($acc->group === 'Liabilitas') {
                $balance = ($acc->normal_balance === 'Kredit') ? ($credit - $debit) : ($debit - $credit);
                $liabilities[] = [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => $balance,
                ];
                $totalLiabilities += $balance;
            } elseif ($acc->group === 'Ekuitas') {
                $balance = ($acc->normal_balance === 'Kredit') ? ($credit - $debit) : ($debit - $credit);
                $equities[] = [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => $balance,
                ];
                $totalEquity += $balance;
            } elseif ($acc->group === 'Pendapatan') {
                $currentYearNetIncome += ($credit - $debit);
            } elseif ($acc->group === 'Beban') {
                $currentYearNetIncome -= ($debit - $credit);
            }
        }

        $totalAssets = $totalCurrentAssets + $totalFixedAssets;
        $totalEquityWithNetIncome = $totalEquity + $currentYearNetIncome;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityWithNetIncome;
        $isBalanced = bccomp((string)$totalAssets, (string)$totalLiabilitiesAndEquity, 2) === 0;

        return [
            'as_of_date' => $asOfDate,
            'assets' => [
                'current_assets' => [
                    'items' => $currentAssets,
                    'total' => $totalCurrentAssets,
                ],
                'fixed_assets' => [
                    'items' => $fixedAssets,
                    'total' => $totalFixedAssets,
                ],
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'items' => $liabilities,
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'items' => $equities,
                'current_period_net_income' => $currentYearNetIncome,
                'total' => $totalEquityWithNetIncome,
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => $isBalanced,
        ];
    }

    /**
     * Dashboard Summary Metrics
     */
    public function getDashboardSummary(int $companyId): array
    {
        $startOfMonth = date('Y-m-01');
        $endOfMonth = date('Y-m-t');

        // Total Kas & Bank (Sum of balance of accounts in group Aset Lancar where code starts with 100)
        $cashAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('category', 'Aset Lancar')
            ->where('code', 'LIKE', '100-%')
            ->get();
        $totalCashBank = $cashAccounts->sum('balance');

        // Income & Expenses this month from CashTransactions or Income Statement
        $incomeStatement = $this->getIncomeStatement($companyId, $startOfMonth, $endOfMonth);

        // Outstanding Receivables
        $unpaidInvoices = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', ['sent', 'overdue'])
            ->get();
        $totalReceivables = $unpaidInvoices->sum(function ($inv) {
            return $inv->total_amount - $inv->paid_amount;
        });

        // Low stock count
        $lowStockProductsCount = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('stock_quantity <= min_stock_alert')
            ->count();

        // Recent cash transactions
        $recentTransactions = CashTransaction::withoutGlobalScopes()
            ->with(['account', 'contraAccount'])
            ->where('company_id', $companyId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return [
            'total_cash_bank' => (float)$totalCashBank,
            'cash_accounts' => $cashAccounts,
            'monthly_income' => (float)$incomeStatement['revenues']['total'],
            'monthly_expense' => (float)$incomeStatement['total_expenses'],
            'net_profit' => (float)$incomeStatement['net_income'],
            'total_receivables' => (float)$totalReceivables,
            'unpaid_invoices_count' => $unpaidInvoices->count(),
            'low_stock_products_count' => $lowStockProductsCount,
            'recent_transactions' => $recentTransactions,
        ];
    }
}
