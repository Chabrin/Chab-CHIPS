<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\InterestRate;
use App\Models\Nav;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function getStats()
    {
        try {
            $companyIds = Auth::user() ? (Auth::user()->role_companies ?? []) : [];

            if (empty($companyIds)) {
                return response()->json(['success' => true, 'stats' => [
                    'pending_members' => 0,
                    'pending_transactions' => 0,
                    'pending_interests' => 0,
                    'pending_navs' => 0,
                    'pending_interest_rates' => 0,
                ]]);
            }

            $stats = [
                'pending_members' => DB::table('members')->where('status', 'pending')->whereIn('company_id', $companyIds)->count()
                    + DB::table('members_host')->where('status', 'pending')->whereIn('company_id', $companyIds)->count(),
                'pending_transactions' => Transaction::where('status', 'pending')->whereIn('company_id', $companyIds)->count(),
                'pending_interests' => 0, // Assuming logic for this is needed
                'pending_navs' => Nav::where('status', 'pending')
                    ->whereHas('fund', function ($q) use ($companyIds) {
                        $q->whereIn('company_id', $companyIds);
                    })->count(),
                'pending_interest_rates' => InterestRate::where('status', 'pending')->count(), // Assuming this is not company-specific
            ];

            return response()->json(['success' => true, 'stats' => $stats]);
        } catch (\Exception $e) {
            Log::error('Error fetching dashboard stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Could not fetch dashboard stats.'], 500);
        }
    }

    /**
     * Get member age distribution data for the dashboard chart.
     */
    public function getAgeDistributionData()
    {
        try {
            $companyIds = Auth::user() ? (Auth::user()->role_companies ?? []) : [];

            if (empty($companyIds)) {
                return response()->json(['success' => true, 'data' => ['labels' => [], 'datasets' => []]]);
            }

            $ageBands = [
                '18-30' => ['start' => 18, 'end' => 30],
                '31-45' => ['start' => 31, 'end' => 45],
                '46-65' => ['start' => 46, 'end' => 65],
                '66-85' => ['start' => 66, 'end' => 85],
                '>85'   => ['start' => 86, 'end' => 200],
            ];

            $companyData = [];

            // Helper function to process members from a given table
            $processMembers = function ($tableName) use (&$companyData, $ageBands, $companyIds) {
                $members = DB::table($tableName)
                    ->leftJoin('companies', "{$tableName}.company_id", '=', 'companies.id')
                    ->whereIn("{$tableName}.company_id", $companyIds)
                    ->whereNotNull("{$tableName}.dob")
                    ->select("{$tableName}.dob", 'companies.company_code')
                    ->get();

                foreach ($members as $member) {
                    $age = Carbon::parse($member->dob)->age;
                    $companyName = $member->company_code ?? 'Uncategorized';

                    if (!isset($companyData[$companyName])) {
                        $companyData[$companyName] = array_fill_keys(array_keys($ageBands), 0);
                    }

                    foreach ($ageBands as $band => $limits) {
                        if ($age >= $limits['start'] && $age <= $limits['end']) {
                            $companyData[$companyName][$band]++;
                            break;
                        }
                    }
                }
            };

            // Process members from both tables
            $processMembers('members');
            $processMembers('members_host');

            $datasets = [];
            foreach ($companyData as $companyName => $bands) {
                if (array_sum($bands) > 0) {
                    $datasets[] = [
                        'label' => $companyName,
                        'data' => array_values($bands),
                        'borderColor' => $this->generateRandomColor(),
                        'backgroundColor' => 'transparent',
                        'borderWidth' => 2,
                        'tension' => 0.4,
                        'fill' => false,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => array_keys($ageBands),
                    'datasets' => $datasets,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching age distribution data: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Could not fetch age distribution data.'], 500);
        }
    }

    /**
     * Generates a random hex color.
     */
    private function generateRandomColor()
    {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
}
