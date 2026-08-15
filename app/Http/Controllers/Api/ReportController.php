<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Laporan Laba Rugi (Income Statement)
     */
    public function incomeStatement(Request $request)
    {
        $companyId = $request->user()->company_id;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = $this->reportService->getIncomeStatement($companyId, $startDate, $endDate);

        return response()->json($report);
    }

    /**
     * Laporan Neraca / Posisi Keuangan (Balance Sheet)
     */
    public function balanceSheet(Request $request)
    {
        $companyId = $request->user()->company_id;
        $asOfDate = $request->input('as_of_date');

        $report = $this->reportService->getBalanceSheet($companyId, $asOfDate);

        return response()->json($report);
    }

    /**
     * Dashboard Summary Metrics
     */
    public function dashboard(Request $request)
    {
        $companyId = $request->user()->company_id;

        $summary = $this->reportService->getDashboardSummary($companyId);

        return response()->json($summary);
    }
}
