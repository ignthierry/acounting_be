<?php

namespace App\Services;

use App\Models\Account;

class CoaTemplateService
{
    /**
     * Standard UMKM (PSAK EMKM) Chart of Accounts Template
     */
    public static function getDefaultTemplate(): array
    {
        return [
            // Aset Lancar (100 - 149)
            ['code' => '100-10', 'name' => 'Kas Tunai', 'category' => 'Aset Lancar', 'group' => 'Aset', 'normal_balance' => 'Debit', 'is_system' => true, 'description' => 'Uang tunai di kasir dan brankas'],
            ['code' => '100-20', 'name' => 'Bank BCA (Operasional)', 'category' => 'Aset Lancar', 'group' => 'Aset', 'normal_balance' => 'Debit', 'is_system' => true, 'description' => 'Rekening bank operasional usaha'],
            ['code' => '120-10', 'name' => 'Piutang Usaha', 'category' => 'Aset Lancar', 'group' => 'Aset', 'normal_balance' => 'Debit', 'is_system' => true, 'description' => 'Tagihan piutang belum dibayar oleh pelanggan'],
            ['code' => '140-10', 'name' => 'Persediaan Barang Dagang', 'category' => 'Aset Lancar', 'group' => 'Aset', 'normal_balance' => 'Debit', 'is_system' => true, 'description' => 'Nilai stok barang dagangan siap jual'],

            // Aset Tetap (150 - 199)
            ['code' => '160-10', 'name' => 'Peralatan & Mesin Usaha', 'category' => 'Aset Tetap', 'group' => 'Aset', 'normal_balance' => 'Debit', 'is_system' => false, 'description' => 'Aset peralatan toko, komputer, rak, dan mesin'],
            ['code' => '160-20', 'name' => 'Akumulasi Penyusutan Peralatan', 'category' => 'Aset Tetap', 'group' => 'Aset', 'normal_balance' => 'Kredit', 'is_system' => false, 'description' => 'Penyusutan nilai peralatan (Kontra Aset)'],

            // Liabilitas (200 - 299)
            ['code' => '200-10', 'name' => 'Utang Usaha / Supplier', 'category' => 'Liabilitas', 'group' => 'Liabilitas', 'normal_balance' => 'Kredit', 'is_system' => true, 'description' => 'Kewajiban tagihan kepada supplier barang'],
            ['code' => '210-10', 'name' => 'Utang Pembiayaan / Bank', 'category' => 'Liabilitas', 'group' => 'Liabilitas', 'normal_balance' => 'Kredit', 'is_system' => false, 'description' => 'Kewajiban pinjaman modal atau pembiayaan usaha'],
            ['code' => '220-10', 'name' => 'Utang Gaji & Beban yang Masih Harus Dibayar', 'category' => 'Liabilitas', 'group' => 'Liabilitas', 'normal_balance' => 'Kredit', 'is_system' => false, 'description' => 'Kewajiban biaya terhutang belum ditransfer'],

            // Ekuitas (300 - 399)
            ['code' => '300-10', 'name' => 'Modal Pemilik', 'category' => 'Ekuitas', 'group' => 'Ekuitas', 'normal_balance' => 'Kredit', 'is_system' => true, 'description' => 'Setoran modal awal pemilik usaha'],
            ['code' => '310-10', 'name' => 'Prive Pemilik (Penarikan)', 'category' => 'Ekuitas', 'group' => 'Ekuitas', 'normal_balance' => 'Debit', 'is_system' => false, 'description' => 'Pengambilan uang usaha untuk keperluan pribadi'],
            ['code' => '320-10', 'name' => 'Laba Ditahan', 'category' => 'Ekuitas', 'group' => 'Ekuitas', 'normal_balance' => 'Kredit', 'is_system' => true, 'description' => 'Akumulasi keuntungan dari periode-periode sebelumnya'],

            // Pendapatan (400 - 499)
            ['code' => '400-10', 'name' => 'Pendapatan Penjualan Barang', 'category' => 'Pendapatan', 'group' => 'Pendapatan', 'normal_balance' => 'Kredit', 'is_system' => true, 'description' => 'Penerimaan omset dari penjualan produk'],
            ['code' => '400-20', 'name' => 'Pendapatan Jasa & Lain-lain', 'category' => 'Pendapatan', 'group' => 'Pendapatan', 'normal_balance' => 'Kredit', 'is_system' => false, 'description' => 'Penerimaan dari jasa atau pendapatan non-produk'],

            // Harga Pokok Penjualan (500 - 599)
            ['code' => '500-10', 'name' => 'Harga Pokok Penjualan (HPP)', 'category' => 'Harga Pokok', 'group' => 'Beban', 'normal_balance' => 'Debit', 'is_system' => true, 'description' => 'Beban biaya pokok dari barang yang terjual'],

            // Beban Operasional (600 - 699)
            ['code' => '600-10', 'name' => 'Beban Gaji & Upah Karyawan', 'category' => 'Beban Operasional', 'group' => 'Beban', 'normal_balance' => 'Debit', 'is_system' => false, 'description' => 'Biaya gaji, bonus, dan tunjangan karyawan'],
            ['code' => '600-20', 'name' => 'Beban Sewa Tempat & Gedung', 'category' => 'Beban Operasional', 'group' => 'Beban', 'normal_balance' => 'Debit', 'is_system' => false, 'description' => 'Biaya sewa toko, gudang, atau kantor'],
            ['code' => '600-30', 'name' => 'Beban Listrik, Air & Internet', 'category' => 'Beban Operasional', 'group' => 'Beban', 'normal_balance' => 'Debit', 'is_system' => false, 'description' => 'Biaya utilitas listrik PLN, PAM, dan Wifi'],
            ['code' => '600-40', 'name' => 'Beban Pemasaran & Iklan', 'category' => 'Beban Operasional', 'group' => 'Beban', 'normal_balance' => 'Debit', 'is_system' => false, 'description' => 'Biaya promosi, media sosial, dan materi iklan'],
            ['code' => '600-50', 'name' => 'Beban Perlengkapan & Operasional Toko', 'category' => 'Beban Operasional', 'group' => 'Beban', 'normal_balance' => 'Debit', 'is_system' => false, 'description' => 'Biaya plastik, lakban, alat tulis kantor'],
            ['code' => '600-60', 'name' => 'Beban Administrasi Bank & Pembiayaan', 'category' => 'Beban Lainnya', 'group' => 'Beban', 'normal_balance' => 'Debit', 'is_system' => false, 'description' => 'Biaya administrasi bank, bagi hasil, dan beban lain'],
        ];
    }

    /**
     * Populate default COA template for a company
     */
    public function seedForCompany(int $companyId): void
    {
        $template = self::getDefaultTemplate();

        foreach ($template as $item) {
            Account::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $item['code'],
                ],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'group' => $item['group'],
                    'normal_balance' => $item['normal_balance'],
                    'balance' => 0.00,
                    'is_system' => $item['is_system'],
                    'description' => $item['description'],
                ]
            );
        }
    }
}
