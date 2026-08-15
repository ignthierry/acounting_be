<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * List all accounts with search and group filter
     */
    public function index(Request $request)
    {
        $query = Account::query();

        if ($request->filled('group') && $request->group !== 'Semua') {
            $query->where('group', $request->group);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('name', 'LIKE', "%{$search}%")
                  ->orWhere('category', 'LIKE', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('code')->get();

        return response()->json([
            'data' => $accounts,
        ]);
    }

    /**
     * Store new custom account
     */
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('accounts')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'group' => 'required|in:Aset,Liabilitas,Ekuitas,Pendapatan,Beban',
            'normal_balance' => 'required|in:Debit,Kredit',
            'balance' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        $account = Account::create([
            'company_id' => $companyId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'category' => $validated['category'],
            'group' => $validated['group'],
            'normal_balance' => $validated['normal_balance'],
            'balance' => $validated['balance'] ?? 0.00,
            'is_system' => false,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Akun perkiraan berhasil ditambahkan.',
            'data' => $account,
        ], 201);
    }

    /**
     * Show account details
     */
    public function show(Account $account)
    {
        return response()->json([
            'data' => $account,
        ]);
    }

    /**
     * Update custom account
     */
    public function update(Request $request, Account $account)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('accounts')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                })->ignore($account->id),
            ],
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'group' => 'required|in:Aset,Liabilitas,Ekuitas,Pendapatan,Beban',
            'normal_balance' => 'required|in:Debit,Kredit',
            'description' => 'nullable|string',
        ]);

        $account->update($validated);

        return response()->json([
            'message' => 'Akun perkiraan berhasil diperbarui.',
            'data' => $account,
        ]);
    }

    /**
     * Delete custom account (not system and no transactions)
     */
    public function destroy(Account $account)
    {
        if ($account->is_system) {
            return response()->json(['message' => 'Akun bawaan sistem tidak dapat dihapus.'], 422);
        }

        if ($account->journalLines()->exists()) {
            return response()->json(['message' => 'Akun yang memiliki riwayat transaksi/jurnal tidak dapat dihapus.'], 422);
        }

        $account->delete();

        return response()->json([
            'message' => 'Akun perkiraan berhasil dihapus.',
        ]);
    }
}
