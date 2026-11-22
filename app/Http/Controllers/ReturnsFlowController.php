<?php

namespace App\Http\Controllers;

use App\Services\ErrorDispatcher;
use App\Services\ReturnsFlowService;
use App\Traits\ChecksMenuAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReturnsFlowController extends Controller
{
    use ChecksMenuAccess;

    const MENU_URL = '/returns/flow';

    protected ReturnsFlowService $flow;

    /**
     * Inject the service that works out where each return is in its flow.
     */
    public function __construct(ReturnsFlowService $flow) {
        $this->flow = $flow;
    }

    /**
     * Show the Returns Flow page: a read-only view of every return's stage, approvals, voucher and payment.
     */
    public function index() {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }

        $years = range((int) date('Y') + 1, (int) date('Y') - 4);
        $stages = $this->flow->stages();

        return view('property.returns.flow', compact('years', 'stages'));
    }

    /**
     * The returns with their flow as JSON for the page's table, narrowed by the page filters.
     */
    public function data(Request $request) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $filters = $request->validate([
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
                'month' => ['nullable', 'integer', 'min:1', 'max:12'],
                'stage' => ['nullable', 'string', 'max:60'],
                'search' => ['nullable', 'string', 'max:100'],
            ]);

            $rows = $this->flow->rows($filters);

            return response()->json(['success' => true, 'data' => $rows, 'limit_reached' => count($rows) >= ReturnsFlowService::LIMIT]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading the returns flow: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load the returns flow'], 500);
        }
    }
}
