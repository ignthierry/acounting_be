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

    /**
     * Generate General Ledger (Buku Besar)
     */
    public function getGeneralLedger(int $companyId, ?int $accountId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?: date('Y-m-01');
        $endDate = $endDate ?: date('Y-m-t');

        // Query accounts
        $accountsQuery = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('code', 'asc');

        if ($accountId) {
            $accountsQuery->where('id', $accountId);
        }

        $accounts = $accountsQuery->get();

        $ledgerData = [];
        $grandTotalDebit = 0.00;
        $grandTotalCredit = 0.00;

        foreach ($accounts as $acc) {
            // 1. Calculate Beginning Balance before $startDate
            $priorLines = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_lines.account_id', $acc->id)
                ->where('journal_entries.entry_date', '<', $startDate)
                ->select(
                    DB::raw('COALESCE(SUM(journal_lines.debit), 0) as prior_debit'),
                    DB::raw('COALESCE(SUM(journal_lines.credit), 0) as prior_credit')
                )
                ->first();

            $priorDebit = (float)($priorLines->prior_debit ?? 0);
            $priorCredit = (float)($priorLines->prior_credit ?? 0);

            if ($acc->normal_balance === 'Debit') {
                $beginningBalance = $priorDebit - $priorCredit;
            } else {
                $beginningBalance = $priorCredit - $priorDebit;
            }

            // 2. Fetch journal lines in date range [$startDate, $endDate]
            $lines = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_lines.account_id', $acc->id)
                ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
                ->select(
                    'journal_lines.id as line_id',
                    'journal_entries.id as entry_id',
                    'journal_entries.entry_number',
                    'journal_entries.entry_date',
                    'journal_entries.reference as reference_number',
                    'journal_entries.description',
                    'journal_entries.source',
                    'journal_lines.debit',
                    'journal_lines.credit',
                    'journal_lines.memo'
                )
                ->orderBy('journal_entries.entry_date', 'asc')
                ->orderBy('journal_entries.id', 'asc')
                ->orderBy('journal_lines.id', 'asc')
                ->get();

            // If all accounts requested, skip accounts with 0 beginning balance and 0 transactions during period
            if (!$accountId && $beginningBalance == 0 && $lines->isEmpty()) {
                continue;
            }

            $currentRunningBalance = $beginningBalance;
            $periodTotalDebit = 0.00;
            $periodTotalCredit = 0.00;
            $transactions = [];

            foreach ($lines as $l) {
                $debit = (float)$l->debit;
                $credit = (float)$l->credit;

                $periodTotalDebit += $debit;
                $periodTotalCredit += $credit;

                if ($acc->normal_balance === 'Debit') {
                    $currentRunningBalance += ($debit - $credit);
                } else {
                    $currentRunningBalance += ($credit - $debit);
                }

                $transactions[] = [
                    'line_id' => $l->line_id,
                    'entry_id' => $l->entry_id,
                    'entry_number' => $l->entry_number,
                    'entry_date' => $l->entry_date,
                    'reference_number' => $l->reference_number,
                    'description' => $l->description,
                    'memo' => $l->memo,
                    'source' => $l->source,
                    'debit' => $debit,
                    'credit' => $credit,
                    'running_balance' => $currentRunningBalance,
                ];
            }

            $grandTotalDebit += $periodTotalDebit;
            $grandTotalCredit += $periodTotalCredit;

            $ledgerData[] = [
                'account' => [
                    'id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'category' => $acc->category,
                    'group' => $acc->group,
                    'normal_balance' => $acc->normal_balance,
                ],
                'beginning_balance' => $beginningBalance,
                'period_debit' => $periodTotalDebit,
                'period_credit' => $periodTotalCredit,
                'net_movement' => ($acc->normal_balance === 'Debit') ? ($periodTotalDebit - $periodTotalCredit) : ($periodTotalCredit - $periodTotalDebit),
                'ending_balance' => $currentRunningBalance,
                'transactions' => $transactions,
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'selected_account_id' => $accountId,
            'grand_total_debit' => $grandTotalDebit,
            'grand_total_credit' => $grandTotalCredit,
            'accounts' => $ledgerData,
        ];
    }

    /**
     * Generate Cash Flow Statement (Laporan Arus Kas - Metode Langsung / Direct Method)
     */
    public function getCashFlowStatement(int $companyId, ?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?: date('Y-m-01');
        $endDate = $endDate ?: date('Y-m-t');

        // 1. Get Cash & Bank Account IDs
        $cashAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('category', 'Aset Lancar')
            ->where('code', 'LIKE', '100-%')
            ->get();
        $cashAccountIds = $cashAccounts->pluck('id')->toArray();

        // 2. Beginning Cash Balance before $startDate
        $priorCashDebit = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_lines.account_id', $cashAccountIds)
            ->where('journal_entries.entry_date', '<', $startDate)
            ->sum('journal_lines.debit');

        $priorCashCredit = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_lines.account_id', $cashAccountIds)
            ->where('journal_entries.entry_date', '<', $startDate)
            ->sum('journal_lines.credit');

        $beginningCash = (float)($priorCashDebit - $priorCashCredit);

        // 3. Query Journal Entries with lines during [$startDate, $endDate]
        $entries = \App\Models\JournalEntry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->with(['lines.account'])
            ->get();

        $operatingInflows = [];
        $operatingOutflows = [];
        $investingInflows = [];
        $investingOutflows = [];
        $financingInflows = [];
        $financingOutflows = [];

        foreach ($entries as $entry) {
            $cashLines = $entry->lines->filter(fn($l) => in_array($l->account_id, $cashAccountIds));
            $nonCashLines = $entry->lines->filter(fn($l) => !in_array($l->account_id, $cashAccountIds));

            if ($cashLines->isEmpty() || $nonCashLines->isEmpty()) {
                continue; // Skip non-cash entries or purely internal cash-to-cash transfers
            }

            $cashDebit = $cashLines->sum('debit');
            $cashCredit = $cashLines->sum('credit');

            // CASE A: Cash Inflow (Debit on Cash)
            if ($cashDebit > 0) {
                foreach ($nonCashLines as $contra) {
                    if ($contra->credit <= 0) continue;
                    $amount = (float)$contra->credit;
                    $account = $contra->account;

                    if ($account->group === 'Pendapatan' || str_starts_with($account->code, '120-') || in_array($entry->source, ['invoice', 'payment'])) {
                        $operatingInflows[] = [
                            'name' => 'Penerimaan Kas dari Pelanggan & Penjualan',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } elseif ($account->group === 'Ekuitas' || str_starts_with($account->code, '300-')) {
                        $financingInflows[] = [
                            'name' => 'Setoran Modal Usaha Pemilik',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } elseif (str_starts_with($account->code, '200-2') || str_starts_with($account->code, '200-3')) {
                        $financingInflows[] = [
                            'name' => 'Penerimaan Pinjaman Bank / Pihak Ketiga',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } elseif (str_starts_with($account->code, '160-')) {
                        $investingInflows[] = [
                            'name' => 'Penjualan Aset Tetap / Peralatan',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } else {
                        $operatingInflows[] = [
                            'name' => 'Penerimaan Kas Operasional Lainnya',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    }
                }
            }

            // CASE B: Cash Outflow (Credit on Cash)
            if ($cashCredit > 0) {
                foreach ($nonCashLines as $contra) {
                    if ($contra->debit <= 0) continue;
                    $amount = (float)$contra->debit;
                    $account = $contra->account;

                    if ($account->category === 'Persediaan' || $account->category === 'Harga Pokok' || $entry->source === 'stock') {
                        $operatingOutflows[] = [
                            'name' => 'Pembelian Persediaan Barang Dagangan',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } elseif ($account->category === 'Beban Operasional' || $account->group === 'Beban') {
                        $operatingOutflows[] = [
                            'name' => 'Pembayaran ' . $account->name,
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } elseif (str_starts_with($account->code, '160-')) {
                        $investingOutflows[] = [
                            'name' => 'Pembelian Aset Tetap & Peralatan Usaha',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } elseif (str_starts_with($account->code, '300-2') || str_starts_with($account->code, '300-3')) {
                        $financingOutflows[] = [
                            'name' => 'Penarikan Modal Pemilik (Prive / Dividen)',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } elseif (str_starts_with($account->code, '200-2') || str_starts_with($account->code, '200-3')) {
                        $financingOutflows[] = [
                            'name' => 'Pelunasan Pokok Pinjaman Bank',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    } else {
                        $operatingOutflows[] = [
                            'name' => 'Pengeluaran Kas Operasional Lainnya',
                            'account' => $account->name,
                            'reference' => $entry->reference ?: $entry->entry_number,
                            'date' => $entry->entry_date,
                            'amount' => $amount,
                        ];
                    }
                }
            }
        }

        // Aggregate by Category Name
        $groupItems = function (array $items) {
            $grouped = [];
            foreach ($items as $it) {
                $name = $it['name'];
                if (!isset($grouped[$name])) {
                    $grouped[$name] = [
                        'name' => $name,
                        'total' => 0.00,
                        'count' => 0,
                    ];
                }
                $grouped[$name]['total'] += $it['amount'];
                $grouped[$name]['count'] += 1;
            }
            return array_values($grouped);
        };

        $groupedOperatingIn = $groupItems($operatingInflows);
        $totalOperatingIn = array_sum(array_column($groupedOperatingIn, 'total'));

        $groupedOperatingOut = $groupItems($operatingOutflows);
        $totalOperatingOut = array_sum(array_column($groupedOperatingOut, 'total'));
        $netOperatingCash = $totalOperatingIn - $totalOperatingOut;

        $groupedInvestingIn = $groupItems($investingInflows);
        $totalInvestingIn = array_sum(array_column($groupedInvestingIn, 'total'));

        $groupedInvestingOut = $groupItems($investingOutflows);
        $totalInvestingOut = array_sum(array_column($groupedInvestingOut, 'total'));
        $netInvestingCash = $totalInvestingIn - $totalInvestingOut;

        $groupedFinancingIn = $groupItems($financingInflows);
        $totalFinancingIn = array_sum(array_column($groupedFinancingIn, 'total'));

        $groupedFinancingOut = $groupItems($financingOutflows);
        $totalFinancingOut = array_sum(array_column($groupedFinancingOut, 'total'));
        $netFinancingCash = $totalFinancingIn - $totalFinancingOut;

        $netCashChange = $netOperatingCash + $netInvestingCash + $netFinancingCash;
        $endingCash = $beginningCash + $netCashChange;

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'operating_activities' => [
                'inflows' => $groupedOperatingIn,
                'total_inflows' => $totalOperatingIn,
                'outflows' => $groupedOperatingOut,
                'total_outflows' => $totalOperatingOut,
                'net_cash' => $netOperatingCash,
            ],
            'investing_activities' => [
                'inflows' => $groupedInvestingIn,
                'total_inflows' => $totalInvestingIn,
                'outflows' => $groupedInvestingOut,
                'total_outflows' => $totalInvestingOut,
                'net_cash' => $netInvestingCash,
            ],
            'financing_activities' => [
                'inflows' => $groupedFinancingIn,
                'total_inflows' => $totalFinancingIn,
                'outflows' => $groupedFinancingOut,
                'total_outflows' => $totalFinancingOut,
                'net_cash' => $netFinancingCash,
            ],
            'summary' => [
                'net_cash_change' => $netCashChange,
                'beginning_cash_balance' => $beginningCash,
                'ending_cash_balance' => $endingCash,
                'cash_accounts' => $cashAccounts->map(fn($acc) => [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => (float)$acc->balance,
                ]),
            ],
        ];
    }
}
