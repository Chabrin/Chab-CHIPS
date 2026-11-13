<?php

namespace App\Http\Controllers;

use App\Exceptions\BankStatementException;
use App\Services\BankStatement\BankStatementBanks;
use App\Services\BankStatement\BankStatementManualMatchService;
use App\Services\ErrorDispatcher;
use App\Traits\ChecksMenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The page for matching one Unmatched or Ambiguous statement line by hand: pick the client, the property and one of its Active
 * leases (a unit with its tenant), then confirm. It sits behind the Bank Statements menu entry. The page and the lookups only send and show; the
 * rules and the payment are in BankStatementManualMatchService.
 */
class BankStatementMatchController extends Controller
{
    use ChecksMenuAccess;

    const MENU_URL = '/tenant/bank-statements';

    protected BankStatementManualMatchService $matching;

    /**
     * @param BankStatementManualMatchService $matching
     */
    public function __construct(BankStatementManualMatchService $matching) {
        $this->matching = $matching;
    }

    /**
     * The matching page for one line.
     *
     * @param string $bank equity or coop
     * @param int $line the statement line
     */
    public function show($bank, $line) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied();
        }
        if (!array_key_exists($bank, BankStatementBanks::all())) {
            abort(404, 'That bank is not one of the statement templates.');
        }

        $page = $this->matching->page($bank, (int) $line, (int) Auth::id());

        if (!$page) {
            abort(404, 'That statement line does not exist.');
        }

        return view('property.payments.bank-statement-match', compact('page'));
    }

    /**
     * Step 1: the clients, found by the text typed (AJAX).
     */
    public function clients(Request $request) {
        return $this->lookup(fn () => $this->matching->clients($this->term($request)), 'clients');
    }

    /**
     * Step 2: the properties of the chosen client (AJAX).
     */
    public function properties(Request $request) {
        return $this->lookup(fn () => $this->matching->properties((int) $request->input('client_id'), $this->term($request)), 'properties');
    }

    /**
     * Step 3: the Active leases of the chosen property, each a unit with its tenant; ended leases too when asked (AJAX).
     */
    public function leases(Request $request) {
        return $this->lookup(fn () => $this->matching->leases((int) $request->input('property_id'), $this->term($request), $request->boolean('include_ended')), 'leases');
    }

    /**
     * Match the line to the chosen lease and pay it in (AJAX).
     *
     * @param string $bank equity or coop
     * @param int $line the statement line
     */
    public function store(Request $request, $bank, $line) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $validated = $request->validate(['unit_id' => 'required|integer', 'lease_id' => 'required|integer'], [
                'unit_id.required' => 'Choose the unit.',
                'lease_id.required' => 'Choose the tenant.',
            ]);

            $result = $this->matching->matchByHand($bank, (int) $line, (int) $validated['unit_id'], (int) $validated['lease_id'], (int) Auth::id());

            return response()->json(['success' => $result['paid']] + $result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (BankStatementException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error matching a bank statement line by hand: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'The line could not be matched and paid, and nothing was changed. Try again, or tell support if it keeps happening.'], 500);
        }
    }

    /**
     * The text typed in a search box, kept to a sensible length.
     */
    private function term(Request $request): string {
        return mb_substr((string) $request->input('q', ''), 0, 100);
    }

    /**
     * Run one lookup behind the menu check and answer with its rows as JSON (AJAX).
     *
     * @param callable $find returns the rows
     * @param string $what what is being looked up, for the error log
     */
    private function lookup(callable $find, string $what) {
        if (!$this->menuAllowed(self::MENU_URL)) {
            return $this->denied('json');
        }

        try {
            return response()->json(['success' => true] + $find());
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error looking up ' . $what . ' for matching a bank statement line: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to load the ' . $what], 500);
        }
    }
}
