<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\JournalService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * List all products with stock and status
     */
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock_only')) {
            $query->whereRaw('stock_quantity <= min_stock_alert');
        }

        $products = $query->orderBy('name')->get()->map(function ($product) {
            $status = 'safe';
            if ($product->stock_quantity <= 0) {
                $status = 'out';
            } elseif ($product->stock_quantity <= ($product->min_stock_alert / 2)) {
                $status = 'critical';
            } elseif ($product->stock_quantity <= $product->min_stock_alert) {
                $status = 'warning';
            }

            $product->status = $status;
            $product->total_inventory_value = round($product->stock_quantity * $product->cost_price, 2);
            return $product;
        });

        $totalInventoryValue = $products->sum('total_inventory_value');
        $lowStockCount = $products->whereIn('status', ['warning', 'critical'])->count();
        $outOfStockCount = $products->where('status', 'out')->count();

        return response()->json([
            'summary' => [
                'total_inventory_value' => $totalInventoryValue,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_products' => $products->count(),
            ],
            'data' => $products,
        ]);
    }

    /**
     * Store new product
     */
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
            'unit' => 'nullable|string|max:50',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|numeric|min:0',
            'min_stock_alert' => 'nullable|numeric|min:0',
        ]);

        $product = Product::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'unit' => $validated['unit'] ?? 'Pcs',
            'selling_price' => $validated['selling_price'],
            'cost_price' => $validated['cost_price'] ?? 0.00,
            'stock_quantity' => $validated['stock_quantity'] ?? 0.00,
            'min_stock_alert' => $validated['min_stock_alert'] ?? 5.00,
        ]);

        return response()->json([
            'message' => 'Produk baru berhasil didaftarkan.',
            'data' => $product,
        ], 201);
    }

    /**
     * Update product details
     */
    public function update(Request $request, Product $product)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                })->ignore($product->id),
            ],
            'unit' => 'nullable|string|max:50',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'min_stock_alert' => 'nullable|numeric|min:0',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Data produk berhasil diperbarui.',
            'data' => $product,
        ]);
    }

    /**
     * Record stock purchase (Pembelian Stok Barang) with average costing
     */
    public function purchaseStock(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_cost' => 'required|numeric|min:0.01',
            'payment_account_id' => 'required|exists:accounts,id', // Kas / Bank / Utang Usaha
            'purchase_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($companyId, $validated) {
            $product = Product::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->find($validated['product_id']);

            $newQty = (float)$validated['quantity'];
            $newUnitCost = (float)$validated['unit_cost'];
            $totalPurchaseCost = round($newQty * $newUnitCost, 2);

            // Calculate moving average cost
            $oldStock = (float)$product->stock_quantity;
            $oldCost = (float)$product->cost_price;

            if ($oldStock + $newQty > 0) {
                $weightedCost = (($oldStock * $oldCost) + $totalPurchaseCost) / ($oldStock + $newQty);
                $product->cost_price = round($weightedCost, 2);
            } else {
                $product->cost_price = $newUnitCost;
            }

            $product->stock_quantity += $newQty;
            $product->save();

            // Journal for Stock Purchase: Debit Persediaan Barang Dagang, Credit Kas/Bank/Utang
            $inventoryAccount = Account::where('code', '140-10')->first() ?: Account::where('category', 'Aset Lancar')->first();
            $paymentAccount = Account::find($validated['payment_account_id']);

            $journalLines = [
                [
                    'account_id' => $inventoryAccount->id,
                    'debit' => $totalPurchaseCost,
                    'credit' => 0,
                    'memo' => "Pembelian stok {$product->name} ({$newQty} {$product->unit})",
                ],
                [
                    'account_id' => $paymentAccount->id,
                    'debit' => 0,
                    'credit' => $totalPurchaseCost,
                    'memo' => "Pembayaran stok {$product->name}",
                ],
            ];

            $journalEntry = $this->journalService->createEntry(
                $companyId,
                $validated['purchase_date'],
                "Pembelian Stok: {$product->name}",
                $journalLines,
                'stock',
                $validated['reference'] ?? null
            );

            // Record movement
            $movement = StockMovement::create([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'type' => 'purchase',
                'quantity' => $newQty,
                'unit_cost' => $newUnitCost,
                'total_cost' => $totalPurchaseCost,
                'movement_date' => $validated['purchase_date'],
                'reference' => $validated['reference'] ?? null,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return response()->json([
                'message' => 'Pembelian stok berhasil dicatat, HPP rata-rata dan jurnal persediaan telah ter-update.',
                'product' => $product,
                'movement' => $movement,
            ], 201);
        });
    }
}
