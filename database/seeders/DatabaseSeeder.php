<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CoaTemplateService;
use App\Services\JournalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $coaService = new CoaTemplateService();
        $journalService = new JournalService();

        // 1. Create Demo Company
        $company = Company::create([
            'name' => 'Toko Sejahtera',
            'email' => 'kontak@tokosejahtera.com',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 45, Jakarta Selatan',
            'currency' => 'IDR',
            'standard' => 'PSAK EMKM',
        ]);

        // 2. Seed COA Template
        $coaService->seedForCompany($company->id);

        // 3. Create Demo Owner User
        $owner = User::create([
            'company_id' => $company->id,
            'name' => 'Budi Santoso',
            'email' => 'budi@tokosejahtera.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        // 4. Initial Capital Injection (Setoran Modal Awal)
        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '100-20')->first();
        $cashAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '100-10')->first();
        $modalAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '300-10')->first();

        $journalService->createEntry(
            $company->id,
            '2026-08-01',
            'Setoran Modal Awal Usaha',
            [
                ['account_id' => $bankAccount->id, 'debit' => 20000000, 'credit' => 0],
                ['account_id' => $cashAccount->id, 'debit' => 5000000, 'credit' => 0],
                ['account_id' => $modalAccount->id, 'debit' => 0, 'credit' => 25000000],
            ],
            'cash',
            'MODAL-001'
        );

        // 5. Create Demo Products
        $p1 = Product::create([
            'company_id' => $company->id,
            'name' => 'Beras Premium 5kg',
            'sku' => 'BRS-PRM-5',
            'unit' => 'Sak',
            'selling_price' => 75000,
            'cost_price' => 68000,
            'stock_quantity' => 45,
            'min_stock_alert' => 10,
        ]);

        $p2 = Product::create([
            'company_id' => $company->id,
            'name' => 'Minyak Goreng 2L',
            'sku' => 'MYK-2L',
            'unit' => 'Pouch',
            'selling_price' => 34000,
            'cost_price' => 31000,
            'stock_quantity' => 12,
            'min_stock_alert' => 15,
        ]);

        $p3 = Product::create([
            'company_id' => $company->id,
            'name' => 'Gula Pasir 1kg',
            'sku' => 'GLA-1KG',
            'unit' => 'Bungkus',
            'selling_price' => 16000,
            'cost_price' => 14500,
            'stock_quantity' => 85,
            'min_stock_alert' => 20,
        ]);

        $inventoryAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '140-10')->first();

        // Initial inventory journal
        $initialInventoryCost = (45 * 68000) + (12 * 31000) + (85 * 14500); // 3060000 + 372000 + 1232500 = 4664500
        $journalService->createEntry(
            $company->id,
            '2026-08-02',
            'Pembelian Stok Awal Produk Sembako',
            [
                ['account_id' => $inventoryAccount->id, 'debit' => $initialInventoryCost, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => $initialInventoryCost],
            ],
            'stock',
            'PO-STK-001'
        );

        // 6. Create Demo Customers
        $c1 = Customer::create([
            'company_id' => $company->id,
            'name' => 'PT Maju Bersama',
            'email' => 'purchasing@majubersama.com',
            'phone' => '08119988776',
            'address' => 'Kawasan Industri Pulogadung, Jakarta Timur',
        ]);

        $c2 = Customer::create([
            'company_id' => $company->id,
            'name' => 'CV Abadi Makmur',
            'email' => 'finance@abadimakmur.com',
            'phone' => '08128877665',
            'address' => 'Jl. Hayam Wuruk No. 12, Jakarta Barat',
        ]);

        // 7. Operating Expenses
        $sewaAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '600-20')->first();
        $listrikAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '600-30')->first();
        $gajiAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '600-10')->first();

        // Sewa ruko
        $journalService->createEntry(
            $company->id,
            '2026-08-03',
            'Pembayaran Sewa Tempat Ruko Toko Bulan Agustus',
            [
                ['account_id' => $sewaAccount->id, 'debit' => 2500000, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 2500000],
            ],
            'expense',
            'KWT-SEWA-01'
        );
        CashTransaction::create([
            'company_id' => $company->id,
            'account_id' => $bankAccount->id,
            'contra_account_id' => $sewaAccount->id,
            'type' => 'out',
            'amount' => 2500000,
            'transaction_date' => '2026-08-03',
            'recipient_vendor' => 'Bpk. Hendra',
            'category' => 'Sewa Tempat',
            'payment_method' => 'Bank Transfer',
            'notes' => 'Pembayaran sewa ruko toko bulan Agustus',
            'reference_number' => 'KWT-SEWA-01',
        ]);

        // Tagihan listrik
        $journalService->createEntry(
            $company->id,
            '2026-08-05',
            'Pembayaran Tagihan Listrik PLN & Wifi Toko',
            [
                ['account_id' => $listrikAccount->id, 'debit' => 850000, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 850000],
            ],
            'expense',
            'PLN-88992'
        );
        CashTransaction::create([
            'company_id' => $company->id,
            'account_id' => $bankAccount->id,
            'contra_account_id' => $listrikAccount->id,
            'type' => 'out',
            'amount' => 850000,
            'transaction_date' => '2026-08-05',
            'recipient_vendor' => 'PLN & Telkom',
            'category' => 'Utilitas',
            'payment_method' => 'Bank Transfer',
            'notes' => 'Pembayaran listrik & internet',
            'reference_number' => 'PLN-88992',
        ]);

        // Gaji staff
        $journalService->createEntry(
            $company->id,
            '2026-08-10',
            'Pembayaran Gaji Karyawan Toko',
            [
                ['account_id' => $gajiAccount->id, 'debit' => 6000000, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 6000000],
            ],
            'expense',
            'SLIP-GAJI-01'
        );
        CashTransaction::create([
            'company_id' => $company->id,
            'account_id' => $bankAccount->id,
            'contra_account_id' => $gajiAccount->id,
            'type' => 'out',
            'amount' => 6000000,
            'transaction_date' => '2026-08-10',
            'recipient_vendor' => 'Staff Toko (3 Orang)',
            'category' => 'Gaji & Upah',
            'payment_method' => 'Bank Transfer',
            'notes' => 'Gaji karyawan toko periode Agustus',
            'reference_number' => 'SLIP-GAJI-01',
        ]);

        // 8. Demo Invoices & Sales
        $piutangAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '120-10')->first();
        $salesAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '400-10')->first();
        $hppAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '500-10')->first();

        // Invoice 1: INV-202608-0001 (Lunas)
        $inv1Amount = 4500000;
        $inv1Cogs = 3600000;
        $jInv1 = $journalService->createEntry(
            $company->id,
            '2026-08-06',
            'Penerbitan Invoice INV-202608-0001 kepada PT Maju Bersama',
            [
                ['account_id' => $piutangAccount->id, 'debit' => $inv1Amount, 'credit' => 0],
                ['account_id' => $salesAccount->id, 'debit' => 0, 'credit' => $inv1Amount],
                ['account_id' => $hppAccount->id, 'debit' => $inv1Cogs, 'credit' => 0],
                ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $inv1Cogs],
            ],
            'invoice',
            'INV-202608-0001'
        );

        $inv1 = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $c1->id,
            'invoice_number' => 'INV-202608-0001',
            'issue_date' => '2026-08-06',
            'due_date' => '2026-08-20',
            'status' => 'paid',
            'subtotal' => 4500000,
            'tax' => 0,
            'total_amount' => 4500000,
            'paid_amount' => 4500000,
            'notes' => 'Pengiriman beras dan minyak pesanan kantin',
            'journal_entry_id' => $jInv1->id,
        ]);

        $inv1->items()->create([
            'product_id' => $p1->id,
            'item_name' => 'Beras Premium 5kg (60 Sak)',
            'quantity' => 60,
            'unit_price' => 75000,
            'subtotal' => 4500000,
        ]);

        // Payment for Invoice 1
        $journalService->createEntry(
            $company->id,
            '2026-08-12',
            'Pelunasan Pembayaran Invoice INV-202608-0001',
            [
                ['account_id' => $bankAccount->id, 'debit' => 4500000, 'credit' => 0],
                ['account_id' => $piutangAccount->id, 'debit' => 0, 'credit' => 4500000],
            ],
            'payment',
            'PAY-202608-0001'
        );

        // Invoice 2: INV-202608-0002 (Sent / Belum Lunas)
        $inv2Amount = 3200000;
        $inv2Cogs = 2500000;
        $jInv2 = $journalService->createEntry(
            $company->id,
            '2026-08-11',
            'Penerbitan Invoice INV-202608-0002 kepada CV Abadi Makmur',
            [
                ['account_id' => $piutangAccount->id, 'debit' => $inv2Amount, 'credit' => 0],
                ['account_id' => $salesAccount->id, 'debit' => 0, 'credit' => $inv2Amount],
                ['account_id' => $hppAccount->id, 'debit' => $inv2Cogs, 'credit' => 0],
                ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $inv2Cogs],
            ],
            'invoice',
            'INV-202608-0002'
        );

        $inv2 = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $c2->id,
            'invoice_number' => 'INV-202608-0002',
            'issue_date' => '2026-08-11',
            'due_date' => '2026-08-25',
            'status' => 'sent',
            'subtotal' => 3200000,
            'tax' => 0,
            'total_amount' => 3200000,
            'paid_amount' => 0,
            'notes' => 'Jatuh tempo 14 hari',
            'journal_entry_id' => $jInv2->id,
        ]);

        $inv2->items()->create([
            'product_id' => $p3->id,
            'item_name' => 'Gula Pasir 1kg (200 Bungkus)',
            'quantity' => 200,
            'unit_price' => 16000,
            'subtotal' => 3200000,
        ]);
    }
}
