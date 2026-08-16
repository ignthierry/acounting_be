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

    /**
     * Buku Besar (General Ledger)
     */
    public function generalLedger(Request $request)
    {
        $companyId = $request->user()->company_id;
        $accountId = $request->input('account_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = $this->reportService->getGeneralLedger(
            $companyId,
            $accountId ? (int)$accountId : null,
            $startDate,
            $endDate
        );

        return response()->json($report);
    }

    /**
     * Laporan Arus Kas (Cash Flow Statement)
     */
    public function cashFlowStatement(Request $request)
    {
        $companyId = $request->user()->company_id;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = $this->reportService->getCashFlowStatement($companyId, $startDate, $endDate);

        return response()->json($report);
    }

    /**
     * Laporan Pajak PPh Final UMKM & Rekapitulasi SPT Tahunan (PP 55/2022)
     */
    public function taxFinalReport(Request $request)
    {
        $companyId = $request->user()->company_id;
        $year = (int)($request->input('year') ?: date('Y'));

        $report = $this->reportService->getTaxFinalReport($companyId, $year);

        return response()->json($report);
    }
}
