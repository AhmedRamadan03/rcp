<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\FinancialReportExport;
use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class FinancialReportController extends Controller
{
    public function __construct(
        protected FinancialReportService $financialReportService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string',
        ]);

        $report = $this->financialReportService->generateReport(
            $validated['start_date'],
            $validated['end_date'],
            $validated['user_id'] ?? null,
            $validated['status'] ?? null
        );

        return response()->json([
            "succes" => true,
            'message' => 'Financial report generated successfully',
            'data' => $report
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string',
        ]);

        $fileName = 'financial-report-' . Carbon::now()->format('Y-m-d-H-i-s') . '.xlsx';

        return Excel::download(
            new FinancialReportExport(
                $validated['start_date'],
                $validated['end_date'],
                $validated['user_id'] ?? null,
                $validated['status'] ?? null
            ),
            $fileName
        );
    }
}
