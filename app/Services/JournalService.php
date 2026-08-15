<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Exception;
use Illuminate\Support\Facades\DB;

class JournalService
{
    /**
     * Create a balanced journal entry
     *
     * @param int $companyId
     * @param string $date (Y-m-d)
     * @param string $description
     * @param array $lines [ ['account_id' => 1, 'debit' => 1000, 'credit' => 0, 'memo' => '...'], ... ]
     * @param string $source ('manual', 'cash', 'expense', 'invoice', 'payment', 'stock')
     * @param string|null $reference
     * @return JournalEntry
     * @throws Exception
     */
    public function createEntry(
        int $companyId,
        string $date,
        string $description,
        array $lines,
        string $source = 'manual',
        ?string $reference = null
    ): JournalEntry {
        return DB::transaction(function () use ($companyId, $date, $description, $lines, $source, $reference) {
            $totalDebit = 0.00;
            $totalCredit = 0.00;

            foreach ($lines as $line) {
                $totalDebit += round((float)($line['debit'] ?? 0), 2);
                $totalCredit += round((float)($line['credit'] ?? 0), 2);
            }

            $totalDebit = round($totalDebit, 2);
            $totalCredit = round($totalCredit, 2);

            // Double-entry validation: Debit must equal Credit
            if (bccomp((string)$totalDebit, (string)$totalCredit, 2) !== 0) {
                throw new Exception("Entri jurnal tidak balance! Total Debit (Rp " . number_format($totalDebit, 2) . ") != Total Kredit (Rp " . number_format($totalCredit, 2) . ")");
            }

            if ($totalDebit <= 0) {
                throw new Exception("Nominal transaksi jurnal harus lebih besar dari 0!");
            }

            // Generate entry number
            $yearMonth = date('Ym', strtotime($date));
            $count = JournalEntry::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('entry_number', 'LIKE', "JRN-{$yearMonth}-%")
                ->count() + 1;
            $entryNumber = sprintf("JRN-%s-%04d", $yearMonth, $count);

            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_number' => $entryNumber,
                'entry_date' => $date,
                'reference' => $reference,
                'description' => $description,
                'source' => $source,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ]);

            foreach ($lines as $line) {
                $debit = round((float)($line['debit'] ?? 0), 2);
                $credit = round((float)($line['credit'] ?? 0), 2);

                if ($debit == 0 && $credit == 0) {
                    continue;
                }

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'memo' => $line['memo'] ?? null,
                ]);

                // Update account balance
                $account = Account::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->find($line['account_id']);

                if ($account) {
                    if ($account->normal_balance === 'Debit') {
                        $account->balance += ($debit - $credit);
                    } else {
                        $account->balance += ($credit - $debit);
                    }
                    $account->save();
                }
            }

            return $entry;
        });
    }
}
