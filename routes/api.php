<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashTransactionController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Luvion Accurix
|--------------------------------------------------------------------------
*/

// Public Authentication Endpoints
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected Endpoints (Requires Sanctum Token)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/invite', [AuthController::class, 'invite']);

    // Dashboard Overview
    Route::get('/dashboard/summary', [ReportController::class, 'dashboard']);

    // Master Chart of Accounts (COA)
    Route::apiResource('accounts', AccountController::class);

    // General Journal (Jurnal Umum)
    Route::apiResource('journals', JournalController::class)->only(['index', 'store', 'show']);

    // Cash, Bank, Expenses & Financing (Kas & Pembiayaan)
    Route::get('/cash-transactions', [CashTransactionController::class, 'index']);
    Route::post('/cash-transactions', [CashTransactionController::class, 'store']);
    Route::post('/cash-transactions/transfer', [CashTransactionController::class, 'transfer']);

    // Customers (Pelanggan)
    Route::apiResource('customers', CustomerController::class);

    // Products & Inventory (Stok & HPP)
    Route::apiResource('products', ProductController::class)->except(['destroy']);
    Route::post('/products/purchase', [ProductController::class, 'purchaseStock']);

    // Invoices & Receivables (Invoice & Piutang)
    Route::get('/invoices/aging-report', [InvoiceController::class, 'agingReport']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'addPayment']);

    // Financial Reports (Laporan Keuangan)
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement']);
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet']);
});
