<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
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

        echo " - [1/9] Creating Company..." . PHP_EOL;
        $company = Company::create([
            'name' => 'Toko Sejahtera',
            'email' => 'kontak@tokosejahtera.com',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 45, Jakarta Selatan',
            'currency' => 'IDR',
            'standard' => 'PSAK EMKM',
        ]);

        echo " - [2/9] Seeding COA..." . PHP_EOL;
        $coaService->seedForCompany($company->id);

        echo " - [3/9] Creating Users..." . PHP_EOL;
        $owner = User::create([
            'company_id' => $company->id,
            'name' => 'Budi Santoso',
            'email' => 'budi@tokosejahtera.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $staff = User::create([
            'company_id' => $company->id,
            'name' => 'Siti Rahmawati',
            'email' => 'siti@tokosejahtera.com',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);

        echo " - [4/9] Initial Capital..." . PHP_EOL;

        // Fetch Important Accounts
        $cashAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '100-10')->first();
        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '100-20')->first();
        $piutangAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '120-10')->first();
        $inventoryAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '140-10')->first();
        $peralatanAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '160-10')->first();
        $modalAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '300-10')->first();
        $salesAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '400-10')->first();
        $hppAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '500-10')->first();
        $gajiAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '600-10')->first();
        $sewaAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '600-20')->first();
        $listrikAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '600-30')->first();
        $atkAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '600-50')->first();

        // 4. Initial Capital Injection (Setoran Modal Pemilik)
        // Bank BCA: 35.000.000, Kas Tunai: 10.000.000 -> Total Modal: 45.000.000
        $journalService->createEntry(
            $company->id,
            '2026-08-01',
            'Setoran Modal Awal Usaha Pemilik',
            [
                ['account_id' => $bankAccount->id, 'debit' => 35000000, 'credit' => 0],
                ['account_id' => $cashAccount->id, 'debit' => 10000000, 'credit' => 0],
                ['account_id' => $modalAccount->id, 'debit' => 0, 'credit' => 45000000],
            ],
            'cash',
            'MODAL-001'
        );

        echo " - [5/9] Equipment purchase..." . PHP_EOL;
        // 5. Pembelian Peralatan & Aset Toko (Rak Display & Komputer Kasir)
        $journalService->createEntry(
            $company->id,
            '2026-08-01',
            'Pembelian Rak Display Toko & Komputer Kasir POS',
            [
                ['account_id' => $peralatanAccount->id, 'debit' => 6500000, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 6500000],
            ],
            'expense',
            'AST-001'
        );
        CashTransaction::create([
            'company_id' => $company->id,
            'account_id' => $bankAccount->id,
            'contra_account_id' => $peralatanAccount->id,
            'type' => 'out',
            'amount' => 6500000,
            'transaction_date' => '2026-08-01',
            'recipient_vendor' => 'Toko Sentosa Display',
            'category' => 'Peralatan Toko',
            'payment_method' => 'Bank Transfer',
            'notes' => 'Pembelian rak display dan set komputer kasir',
            'reference_number' => 'AST-001',
        ]);

        echo " - [6/9] Creating Contacts..." . PHP_EOL;
        // 6. Create Demo Contacts (Pelanggan & Vendor)
        $c1 = Customer::create([
            'company_id' => $company->id,
            'name' => 'PT Maju Bersama',
            'type' => 'customer',
            'email' => 'purchasing@majubersama.com',
            'phone' => '08119988776',
            'address' => 'Kawasan Industri Pulogadung Blok B3, Jakarta Timur',
            'notes' => 'Termin pembayaran Net 14 hari',
        ]);

        $c2 = Customer::create([
            'company_id' => $company->id,
            'name' => 'CV Abadi Makmur',
            'type' => 'customer',
            'email' => 'finance@abadimakmur.com',
            'phone' => '08128877665',
            'address' => 'Jl. Hayam Wuruk No. 12, Jakarta Barat',
            'notes' => 'Pelanggan toko grosir langganan',
        ]);

        $c3 = Customer::create([
            'company_id' => $company->id,
            'name' => 'Resto Nusantara Rasa',
            'type' => 'customer',
            'email' => 'resto@nusantararasa.id',
            'phone' => '08137788990',
            'address' => 'Jl. Senopati No. 88, Jakarta Selatan',
            'notes' => 'Restoran pelanggan beras & minyak rutin tiap minggu',
        ]);

        $v1 = Customer::create([
            'company_id' => $company->id,
            'name' => 'PT Pangan Sejati Nusantara',
            'type' => 'vendor',
            'email' => 'sales@pangansejati.co.id',
            'phone' => '08156677889',
            'address' => 'Jl. Industri Raya KM 18, Cikarang, Bekasi',
            'notes' => 'Supplier utama beras kualitas super & gula pasir',
        ]);

        $v2 = Customer::create([
            'company_id' => $company->id,
            'name' => 'Distributor Minyak Sawit Murni',
            'type' => 'vendor',
            'email' => 'order@sawitmurni.com',
            'phone' => '08192233445',
            'address' => 'Pergudangan Marunda Center Kav. 5, Jakarta Utara',
            'notes' => 'Supplier resmi minyak goreng kemasan karton',
        ]);

        echo " - [7/9] Creating Products & Stock..." . PHP_EOL;

        // 7. Create Demo Products & Inventory
        $p1 = Product::create([
            'company_id' => $company->id,
            'name' => 'Beras Pandan Wangi Premium 5kg',
            'sku' => 'BRS-PW-5',
            'unit' => 'Sak',
            'selling_price' => 78000,
            'cost_price' => 68000,
            'stock_quantity' => 80,
            'min_stock_alert' => 20,
        ]);

        $p2 = Product::create([
            'company_id' => $company->id,
            'name' => 'Minyak Goreng Sawit 2L',
            'sku' => 'MYK-SWT-2L',
            'unit' => 'Pouch',
            'selling_price' => 35000,
            'cost_price' => 31000,
            'stock_quantity' => 65,
            'min_stock_alert' => 15,
        ]);

        $p3 = Product::create([
            'company_id' => $company->id,
            'name' => 'Gula Pasir Kristal Putih 1kg',
            'sku' => 'GLA-PST-1K',
            'unit' => 'Kg',
            'selling_price' => 16500,
            'cost_price' => 14500,
            'stock_quantity' => 120,
            'min_stock_alert' => 25,
        ]);

        $p4 = Product::create([
            'company_id' => $company->id,
            'name' => 'Tepung Terigu Serbaguna 1kg',
            'sku' => 'TPG-TRG-1K',
            'unit' => 'Bungkus',
            'selling_price' => 13000,
            'cost_price' => 11000,
            'stock_quantity' => 70,
            'min_stock_alert' => 15,
        ]);

        // Initial inventory purchase journal:
        // Total Stock Value = (80 * 68000) + (65 * 31000) + (120 * 14500) + (70 * 11000)
        // 5.440.000 + 2.015.000 + 1.740.000 + 770.000 = 9.965.000
        $initialInventoryCost = 9965000;
        $journalService->createEntry(
            $company->id,
            '2026-08-02',
            'Pembelian Stok Awal Produk Sembako dari PT Pangan Sejati Nusantara',
            [
                ['account_id' => $inventoryAccount->id, 'debit' => $initialInventoryCost, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => $initialInventoryCost],
            ],
            'stock',
            'PO-STK-001'
        );

        echo " - [8/9] Recording Expenses..." . PHP_EOL;
        // 8. Operating Expenses (Biaya Operasional)
        // A. Sewa Ruko Bulan Agustus (Rp 3.000.000)
        $journalService->createEntry(
            $company->id,
            '2026-08-03',
            'Pembayaran Sewa Tempat Ruko Toko Bulan Agustus',
            [
                ['account_id' => $sewaAccount->id, 'debit' => 3000000, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 3000000],
            ],
            'expense',
            'KWT-SEWA-01'
        );
        CashTransaction::create([
            'company_id' => $company->id,
            'account_id' => $bankAccount->id,
            'contra_account_id' => $sewaAccount->id,
            'type' => 'out',
            'amount' => 3000000,
            'transaction_date' => '2026-08-03',
            'recipient_vendor' => 'Bpk. Hendra Wijaya',
            'category' => 'Sewa Tempat',
            'payment_method' => 'Bank Transfer',
            'notes' => 'Pembayaran sewa ruko toko bulan Agustus 2026',
            'reference_number' => 'KWT-SEWA-01',
        ]);

        // B. Listrik PLN & Internet (Rp 950.000)
        $journalService->createEntry(
            $company->id,
            '2026-08-05',
            'Pembayaran Tagihan Listrik PLN & Internet Toko',
            [
                ['account_id' => $listrikAccount->id, 'debit' => 950000, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 950000],
            ],
            'expense',
            'PLN-88992'
        );
        CashTransaction::create([
            'company_id' => $company->id,
            'account_id' => $bankAccount->id,
            'contra_account_id' => $listrikAccount->id,
            'type' => 'out',
            'amount' => 950000,
            'transaction_date' => '2026-08-05',
            'recipient_vendor' => 'PLN & Telkom Indonesia',
            'category' => 'Utilitas',
            'payment_method' => 'Bank Transfer',
            'notes' => 'Pembayaran listrik toko dan internet fiber',
            'reference_number' => 'PLN-88992',
        ]);

        // C. Gaji Karyawan Toko (Rp 5.500.000)
        $journalService->createEntry(
            $company->id,
            '2026-08-10',
            'Pembayaran Gaji Karyawan Toko Periode Agustus',
            [
                ['account_id' => $gajiAccount->id, 'debit' => 5500000, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 5500000],
            ],
            'expense',
            'SLIP-GAJI-01'
        );
        CashTransaction::create([
            'company_id' => $company->id,
            'account_id' => $bankAccount->id,
            'contra_account_id' => $gajiAccount->id,
            'type' => 'out',
            'amount' => 5500000,
            'transaction_date' => '2026-08-10',
            'recipient_vendor' => 'Staff Toko (2 Orang)',
            'category' => 'Gaji & Upah',
            'payment_method' => 'Bank Transfer',
            'notes' => 'Gaji karyawan toko bulan Agustus 2026',
            'reference_number' => 'SLIP-GAJI-01',
        ]);

        // D. ATK & Perlengkapan Toko Kas Tunai (Rp 350.000)
        $journalService->createEntry(
            $company->id,
            '2026-08-12',
            'Pembelian Kertas Struk, Plastik Packing & ATK',
            [
                ['account_id' => $atkAccount->id, 'debit' => 350000, 'credit' => 0],
                ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 350000],
            ],
            'expense',
            'ATK-001'
        );
        CashTransaction::create([
            'company_id' => $company->id,
            'account_id' => $cashAccount->id,
            'contra_account_id' => $atkAccount->id,
            'type' => 'out',
            'amount' => 350000,
            'transaction_date' => '2026-08-12',
            'recipient_vendor' => 'Toko ATK Makmur',
            'category' => 'Perlengkapan Usaha',
            'payment_method' => 'Cash',
            'notes' => 'Pembelian kertas nota kasir dan plastik packaging',
            'reference_number' => 'ATK-001',
        ]);

        echo " - [9/9] Generating Invoices & Payments..." . PHP_EOL;
        // 9. Demo Invoices & Real Sales Operations

        // INVOICE 1: PT Maju Bersama (Rp 4.680.000) - Status: LUNAS (Paid)
        // 60 Sak Beras @ 78.000 = 4.680.000 | COGS: 60 * 68.000 = 4.080.000
        $inv1Amount = 4680000;
        $inv1Cogs = 4080000;
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
            'subtotal' => $inv1Amount,
            'tax' => 0,
            'total_amount' => $inv1Amount,
            'paid_amount' => $inv1Amount,
            'notes' => 'Pengiriman beras pesanan operasional kantin PT Maju Bersama',
            'journal_entry_id' => $jInv1->id,
        ]);

        $inv1->items()->create([
            'product_id' => $p1->id,
            'item_name' => 'Beras Pandan Wangi Premium 5kg',
            'quantity' => 60,
            'unit_price' => 78000,
            'subtotal' => 4680000,
        ]);

        // Payment for Invoice 1 via Bank BCA
        $jPay1 = $journalService->createEntry(
            $company->id,
            '2026-08-14',
            'Pelunasan Pembayaran Invoice INV-202608-0001 (PT Maju Bersama)',
            [
                ['account_id' => $bankAccount->id, 'debit' => $inv1Amount, 'credit' => 0],
                ['account_id' => $piutangAccount->id, 'debit' => 0, 'credit' => $inv1Amount],
            ],
            'payment',
            'PAY-202608-0001'
        );
        Payment::create([
            'company_id' => $company->id,
            'invoice_id' => $inv1->id,
            'account_id' => $bankAccount->id,
            'payment_number' => 'PAY-202608-0001',
            'amount' => $inv1Amount,
            'payment_date' => '2026-08-14',
            'payment_method' => 'Transfer',
            'notes' => 'Transfer Bank BCA pelunasan invoice INV-202608-0001',
            'journal_entry_id' => $jPay1->id,
        ]);

        // INVOICE 2: Resto Nusantara Rasa (Rp 3.150.000) - Status: LUNAS (Paid)
        // 90 Pouch Minyak @ 35.000 = 3.150.000 | COGS: 90 * 31.000 = 2.790.000
        $inv2Amount = 3150000;
        $inv2Cogs = 2790000;
        $jInv2 = $journalService->createEntry(
            $company->id,
            '2026-08-08',
            'Penerbitan Invoice INV-202608-0002 kepada Resto Nusantara Rasa',
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
            'customer_id' => $c3->id,
            'invoice_number' => 'INV-202608-0002',
            'issue_date' => '2026-08-08',
            'due_date' => '2026-08-15',
            'status' => 'paid',
            'subtotal' => $inv2Amount,
            'tax' => 0,
            'total_amount' => $inv2Amount,
            'paid_amount' => $inv2Amount,
            'notes' => 'Pasokan minyak goreng resto cabang Senopati',
            'journal_entry_id' => $jInv2->id,
        ]);

        $inv2->items()->create([
            'product_id' => $p2->id,
            'item_name' => 'Minyak Goreng Sawit 2L',
            'quantity' => 90,
            'unit_price' => 35000,
            'subtotal' => 3150000,
        ]);

        // Payment for Invoice 2 via Kas Tunai
        $jPay2 = $journalService->createEntry(
            $company->id,
            '2026-08-09',
            'Penerimaan Pembayaran Tunai Invoice INV-202608-0002 (Resto Nusantara)',
            [
                ['account_id' => $cashAccount->id, 'debit' => $inv2Amount, 'credit' => 0],
                ['account_id' => $piutangAccount->id, 'debit' => 0, 'credit' => $inv2Amount],
            ],
            'payment',
            'PAY-202608-0002'
        );
        Payment::create([
            'company_id' => $company->id,
            'invoice_id' => $inv2->id,
            'account_id' => $cashAccount->id,
            'payment_number' => 'PAY-202608-0002',
            'amount' => $inv2Amount,
            'payment_date' => '2026-08-09',
            'payment_method' => 'Cash',
            'notes' => 'Pembayaran tunai kasir oleh Resto Nusantara Rasa',
            'journal_entry_id' => $jPay2->id,
        ]);

        // INVOICE 3: CV Abadi Makmur (Rp 4.125.000) - Status: BELUM LUNAS (Sent)
        // 250 Kg Gula @ 16.500 = 4.125.000 | COGS: 250 * 14.500 = 3.625.000
        $inv3Amount = 4125000;
        $inv3Cogs = 3625000;
        $jInv3 = $journalService->createEntry(
            $company->id,
            '2026-08-11',
            'Penerbitan Invoice INV-202608-0003 kepada CV Abadi Makmur',
            [
                ['account_id' => $piutangAccount->id, 'debit' => $inv3Amount, 'credit' => 0],
                ['account_id' => $salesAccount->id, 'debit' => 0, 'credit' => $inv3Amount],
                ['account_id' => $hppAccount->id, 'debit' => $inv3Cogs, 'credit' => 0],
                ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $inv3Cogs],
            ],
            'invoice',
            'INV-202608-0003'
        );

        $inv3 = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $c2->id,
            'invoice_number' => 'INV-202608-0003',
            'issue_date' => '2026-08-11',
            'due_date' => '2026-08-25',
            'status' => 'sent',
            'subtotal' => $inv3Amount,
            'tax' => 0,
            'total_amount' => $inv3Amount,
            'paid_amount' => 0,
            'notes' => 'Pesanan gula pasir grosir, termin 14 hari',
            'journal_entry_id' => $jInv3->id,
        ]);

        $inv3->items()->create([
            'product_id' => $p3->id,
            'item_name' => 'Gula Pasir Kristal Putih 1kg',
            'quantity' => 250,
            'unit_price' => 16500,
            'subtotal' => 4125000,
        ]);

        // INVOICE 4: Resto Nusantara Rasa (Rp 1.740.000) - Status: JATUH TEMPO (Overdue)
        // 15 Sak Beras @ 78.000 = 1.170.000 + 15 Pouch Minyak @ 38.000 = 570.000 -> Total 1.740.000
        // COGS: (15 * 68.000) + (15 * 31.000) = 1.020.000 + 465.000 = 1.485.000
        $inv4Amount = 1740000;
        $inv4Cogs = 1485000;
        $jInv4 = $journalService->createEntry(
            $company->id,
            '2026-07-28',
            'Penerbitan Invoice INV-202607-0089 kepada Resto Nusantara Rasa',
            [
                ['account_id' => $piutangAccount->id, 'debit' => $inv4Amount, 'credit' => 0],
                ['account_id' => $salesAccount->id, 'debit' => 0, 'credit' => $inv4Amount],
                ['account_id' => $hppAccount->id, 'debit' => $inv4Cogs, 'credit' => 0],
                ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $inv4Cogs],
            ],
            'invoice',
            'INV-202607-0089'
        );

        $inv4 = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $c3->id,
            'invoice_number' => 'INV-202607-0089',
            'issue_date' => '2026-07-28',
            'due_date' => '2026-08-11', // sudah lewat dari sekarang (overdue)
            'status' => 'overdue',
            'subtotal' => $inv4Amount,
            'tax' => 0,
            'total_amount' => $inv4Amount,
            'paid_amount' => 0,
            'notes' => 'Tagihan jatuh tempo membutuhkan follow up pembayaran',
            'journal_entry_id' => $jInv4->id,
        ]);

        $inv4->items()->create([
            'product_id' => $p1->id,
            'item_name' => 'Beras Pandan Wangi Premium 5kg',
            'quantity' => 15,
            'unit_price' => 78000,
            'subtotal' => 1170000,
        ]);

        $inv4->items()->create([
            'product_id' => $p2->id,
            'item_name' => 'Minyak Goreng Sawit 2L',
            'quantity' => 15,
            'unit_price' => 38000,
            'subtotal' => 570000,
        ]);
    }
}
