<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * List all contacts (customers / vendors)
     */
    public function index(Request $request)
    {
        $query = Customer::with(['invoices']);

        // Filter by Type (customer, vendor, both)
        if ($request->filled('type') && $request->type !== 'all') {
            $type = $request->type;
            if ($type === 'customer') {
                $query->whereIn('type', ['customer', 'both']);
            } elseif ($type === 'vendor') {
                $query->whereIn('type', ['vendor', 'both']);
            } else {
                $query->where('type', $type);
            }
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->get();

        // Append metrics per contact
        $formatted = $customers->map(function ($c) {
            $totalInvoiced = $c->invoices->sum('total_amount');
            $totalPaid = $c->invoices->sum('paid_amount');
            $outstanding = $totalInvoiced - $totalPaid;
            $overdueCount = $c->invoices->where('status', 'overdue')->count();

            return [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type ?? 'customer',
                'email' => $c->email,
                'phone' => $c->phone,
                'address' => $c->address,
                'notes' => $c->notes,
                'invoices_count' => $c->invoices->count(),
                'total_invoiced' => (float)$totalInvoiced,
                'total_paid' => (float)$totalPaid,
                'outstanding_receivables' => (float)$outstanding,
                'has_overdue' => $overdueCount > 0,
                'created_at' => $c->created_at,
            ];
        });

        return response()->json([
            'data' => $formatted,
            'summary' => [
                'total_contacts' => $customers->count(),
                'total_customers' => $customers->whereIn('type', ['customer', 'both'])->count(),
                'total_vendors' => $customers->whereIn('type', ['vendor', 'both'])->count(),
                'total_receivables' => (float)$formatted->sum('outstanding_receivables'),
            ],
        ]);
    }

    /**
     * Show contact detail with invoice history
     */
    public function show(Customer $customer)
    {
        $customer->load(['invoices.items', 'invoices.payments']);

        $totalInvoiced = $customer->invoices->sum('total_amount');
        $totalPaid = $customer->invoices->sum('paid_amount');
        $outstanding = $totalInvoiced - $totalPaid;

        return response()->json([
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'type' => $customer->type ?? 'customer',
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'total_invoiced' => (float)$totalInvoiced,
                'total_paid' => (float)$totalPaid,
                'outstanding_receivables' => (float)$outstanding,
                'invoices' => $customer->invoices->sortByDesc('issue_date')->values(),
                'created_at' => $customer->created_at,
            ],
        ]);
    }

    /**
     * Store new contact
     */
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:customer,vendor,both',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'customer',
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Kontak mitra bisnis berhasil disimpan.',
            'data' => $customer,
        ], 201);
    }

    /**
     * Update contact
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:customer,vendor,both',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Data kontak berhasil diperbarui.',
            'data' => $customer,
        ]);
    }

    /**
     * Delete contact
     */
    public function destroy(Customer $customer)
    {
        if ($customer->invoices()->exists()) {
            return response()->json(['message' => 'Kontak dengan riwayat transaksi invoice tidak dapat dihapus.'], 422);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Data kontak berhasil dihapus.',
        ]);
    }
}
