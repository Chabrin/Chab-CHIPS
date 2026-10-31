<?php

namespace App\Http\Controllers;

use App\Services\EmployeeContractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContractExpiryOverviewController extends Controller
{
    public function show(Request $request, EmployeeContractReport $report)
    {
        abort_unless($request->user()->hasRole('hr_manager') || $request->user()->hasRole('directors'), 403);

        try {
            return response()->json($report->dashboard())->header('Cache-Control', 'no-store');
        } catch (\Throwable $exception) {
            Log::error('Contract expiry overview could not be loaded', ['exception' => $exception->getMessage()]);
            return response()->json(['message' => 'Unable to load contract expiry figures. Please try again.'], 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}
