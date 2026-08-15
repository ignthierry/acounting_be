<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_auth_login()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'budi@tokosejahtera.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role'],
                'company' => ['id', 'name'],
            ]);
    }

    public function test_get_coa_accounts()
    {
        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_create_balanced_journal_entry()
    {
        $user = User::first();
        $kas = Account::where('code', '100-10')->first();
        $pendapatan = Account::where('code', '400-20')->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/journals', [
                'entry_date' => '2026-08-15',
                'description' => 'Pendapatan Jasa Konsultasi',
                'reference' => 'JSA-001',
                'lines' => [
                    ['account_id' => $kas->id, 'debit' => 500000, 'credit' => 0],
                    ['account_id' => $pendapatan->id, 'debit' => 0, 'credit' => 500000],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_debit', '500000.00')
            ->assertJsonPath('data.total_credit', '500000.00');
    }

    public function test_unbalanced_journal_entry_fails()
    {
        $user = User::first();
        $kas = Account::where('code', '100-10')->first();
        $pendapatan = Account::where('code', '400-20')->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/journals', [
                'entry_date' => '2026-08-15',
                'description' => 'Unbalanced Test',
                'lines' => [
                    ['account_id' => $kas->id, 'debit' => 500000, 'credit' => 0],
                    ['account_id' => $pendapatan->id, 'debit' => 0, 'credit' => 400000], // mismatch!
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_income_statement_report()
    {
        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/reports/income-statement');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'revenues',
                'cogs',
                'gross_profit',
                'operating_expenses',
                'net_income',
            ]);
    }

    public function test_balance_sheet_report()
    {
        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/reports/balance-sheet');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'assets' => ['current_assets', 'fixed_assets', 'total'],
                'liabilities' => ['items', 'total'],
                'equity' => ['items', 'total'],
                'total_liabilities_and_equity',
                'is_balanced',
            ])
            ->assertJsonPath('is_balanced', true);
    }

    public function test_dashboard_summary()
    {
        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_cash_bank',
                'monthly_income',
                'monthly_expense',
                'net_profit',
                'total_receivables',
                'low_stock_products_count',
            ]);
    }
}
