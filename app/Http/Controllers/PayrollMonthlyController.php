<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractChange;
use App\Models\ContractElement;
use App\Models\EmployeeContract;
use App\Models\PayrollElement;
use App\Models\PayrollPeriod;
use App\Models\PayrollPeriodItem;
use App\Models\PayrollRun;
use App\Models\PayrollSummary;
use App\Services\PayrollChangeFeed;
use App\Services\PayrollPreRunCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The page HR uses before running a period: a check of what would be left out or has changed, the one-time items
 * (bonus, arrears, an absence deduction) for that month only, and the recurring items on each staff member's contract.
 */
class PayrollMonthlyController extends Controller
{
    public function __construct(private PayrollPreRunCheck $check, private PayrollChangeFeed $changeFeed)
    {
    }

    /**
     * What has changed since the last payroll that could change pay, as a ready-made panel. HR sees it on the processing
     * screen before pressing Process. It only informs.
     */
    public function changes(int $periodId): JsonResponse
    {
        $this->requirePermission('read_payroll_processing');
        $period = PayrollPeriod::findOrFail($periodId);

        if (!$period->start_date || !$period->end_date) {
            return response()->json(['success' => false, 'error' => 'This period has no start or end date. Set both in the Payroll Calendar.'], 422);
        }

        $changes = $this->changeFeed->forPeriod($period);

        return response()->json([
            'success' => true,
            'changes_total' => $changes['changes_total'],
            'html' => view('payroll.partials.changes-panel', ['changes' => $changes])->render(),
        ]);
    }

    /**
     * Stop the request with a 403 unless the signed-in user holds the given permission.
     */
    private function requirePermission(string $permission): void
    {
        abort_unless(Auth::check() && Auth::user()->isAbleTo($permission), 403, 'You do not have permission to perform this payroll action.');
    }

    /**
     * The page: a period picker that opens on the earliest period not yet processed.
     */
    public function index(Request $request)
    {
        $this->requirePermission('read_payroll_processing');

        $periods = PayrollPeriod::where('current_status_id', 5)->orderByDesc('start_date')->limit(24)->get();
        $processed = PayrollSummary::whereIn('period_id', $periods->pluck('id'))->distinct()->pluck('period_id')->all();

        $selected = $request->integer('period')
            ?: ($periods->reverse()->first(fn ($period) => !in_array($period->id, $processed, true))?->id ?? $periods->first()?->id);

        return view('payroll.monthly', [
            'periods' => $periods->map(fn ($period) => [
                'id' => $period->id,
                'label' => $this->periodLabel($period) . (in_array($period->id, $processed, true) ? ' (processed)' : ''),
            ])->values(),
            'selected' => $selected,
        ]);
    }

    /**
     * Everything the page shows for one period: the check, the one-time items and the lists the forms need.
     */
    public function data(int $periodId): JsonResponse
    {
        $this->requirePermission('read_payroll_processing');
        $period = PayrollPeriod::findOrFail($periodId);

        if (!$period->start_date || !$period->end_date) {
            return response()->json(['success' => false, 'error' => 'This period has no start or end date. Set both in the Payroll Calendar.'], 422);
        }

        $check = $this->check->forPeriod($period);
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'period' => ['id' => $period->id, 'label' => $this->periodLabel($period)],
            'run_status' => PayrollRun::where('period_id', $period->id)->first()?->status_label,
            'locked' => $check['locked'],
            'check' => $check,
            'items' => PayrollPeriodItem::with(['element', 'staff'])->where('period_id', $period->id)->orderBy('id')->get()->map(fn ($item) => [
                'id' => $item->id,
                'staff' => $item->staff?->full_names ?? 'All staff',
                'staff_number' => $item->staff?->staff_number,
                'element' => $item->element?->element_name ?? 'Deleted element',
                'type' => (string) $item->element?->element_type,
                'amount' => (float) $item->amount,
                'note' => $item->note,
            ])->values(),
            'elements' => PayrollElement::approved()->orderBy('element_name')->get()
                ->filter(fn ($element) => $element->canBeOneTimeItem())
                ->map(fn ($element) => ['id' => $element->id, 'name' => $element->element_name, 'type' => (string) $element->element_type])->values(),
            'staff' => $this->staffInRun($period)->map(fn ($contract) => [
                'id' => $contract->staff_id,
                'name' => $contract->staff->full_names,
                'number' => $contract->staff->staff_number,
            ])->values(),
            'can_edit_items' => $user->isAbleTo('create_payroll_processing'),
            'can_edit_recurring' => $user->isAbleTo('update_contracts'),
        ]);
    }

    /**
     * Add a one-time item for this period, for one staff member or for everyone in the run.
     */
    public function storeItem(Request $request, int $periodId): JsonResponse
    {
        $this->requirePermission('create_payroll_processing');
        $period = PayrollPeriod::findOrFail($periodId);

        if ($locked = $this->lockedResponse($period)) {
            return $locked;
        }

        $validated = $request->validate([
            'element_id' => 'required|integer|exists:sys_hr_payroll_elements,id',
            'staff_id' => 'nullable|integer',
            'apply_to_all' => 'nullable|boolean',
            'amount' => 'required|numeric|gt:0|max:99999999',
            'note' => 'nullable|string|max:255',
        ]);

        $element = PayrollElement::findOrFail($validated['element_id']);
        if (!$element->canBeOneTimeItem()) {
            return $this->fail("{$element->element_name} is not a one-time item. Set it to One-time under Payroll Elements first.");
        }

        $everyone = $request->boolean('apply_to_all');
        $staffId = $everyone ? null : ($validated['staff_id'] ?? null);

        if (!$everyone && !$staffId) {
            return $this->fail('Choose a staff member, or apply the item to all staff.');
        }
        if ($staffId && !$this->staffInRun($period)->contains('staff_id', (int) $staffId)) {
            return $this->fail('That staff member has no current contract in this period, so the run would skip the item.');
        }
        if (PayrollPeriodItem::where('period_id', $period->id)->where('element_id', $element->id)->where('staff_id', $staffId)->exists()) {
            return $this->fail("{$element->element_name} is already entered for " . ($staffId ? 'this staff member' : 'all staff') . ' in this period. Edit that entry instead.');
        }

        $item = PayrollPeriodItem::create([
            'period_id' => $period->id,
            'staff_id' => $staffId,
            'element_id' => $element->id,
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'id' => $item->id]);
    }

    /**
     * Change the amount or note of a one-time item.
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $this->requirePermission('create_payroll_processing');
        $item = PayrollPeriodItem::findOrFail($itemId);

        if ($locked = $this->lockedResponse(PayrollPeriod::findOrFail($item->period_id))) {
            return $locked;
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0|max:99999999',
            'note' => 'nullable|string|max:255',
        ]);

        $item->update(['amount' => $validated['amount'], 'note' => $validated['note'] ?? null]);

        return response()->json(['success' => true]);
    }

    /**
     * Remove a one-time item.
     */
    public function deleteItem(int $itemId): JsonResponse
    {
        $this->requirePermission('create_payroll_processing');
        $item = PayrollPeriodItem::findOrFail($itemId);

        if ($locked = $this->lockedResponse(PayrollPeriod::findOrFail($item->period_id))) {
            return $locked;
        }

        $item->delete();

        return response()->json(['success' => true]);
    }

    /**
     * The recurring items on the contracts of the staff in this period's run, one row per item.
     */
    public function recurring(int $periodId): JsonResponse
    {
        $this->requirePermission('read_payroll_processing');
        $period = PayrollPeriod::findOrFail($periodId);

        if (!$period->start_date || !$period->end_date) {
            return response()->json(['success' => false, 'error' => 'This period has no start or end date.'], 422);
        }

        $contracts = $this->staffInRun($period)->keyBy('id');

        $rows = ContractElement::with('payrollElement')->whereIn('parent_id', $contracts->keys())->get()
            ->filter(fn ($row) => $row->payrollElement?->canBeOnContract())
            ->map(fn ($row) => [
                'row_id' => $row->id,
                'contract_id' => $row->parent_id,
                'staff' => $contracts[$row->parent_id]->staff->full_names,
                'staff_number' => $contracts[$row->parent_id]->staff->staff_number,
                'element' => $row->payrollElement->element_name,
                'type' => (string) $row->payrollElement->element_type,
                'amount' => (float) $row->amount,
                // How often it applies and what a part month does to it, as set on the contract
                'terms' => $row->termsText(),
            ])
            ->sortBy([['staff', 'asc'], ['element', 'asc']])
            ->values();

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'contracts' => $contracts->map(fn ($contract) => [
                'id' => $contract->id,
                'name' => $contract->staff->full_names,
                'number' => $contract->staff->staff_number,
            ])->sortBy('name')->values(),
            'elements' => PayrollElement::approved()->orderBy('element_name')->get()
                ->filter(fn ($element) => $element->canBeOnContract())
                ->map(fn ($element) => [
                    'id' => $element->id,
                    'name' => $element->element_name,
                    'type' => (string) $element->element_type,
                    'default' => (float) $element->amount,
                    'formula' => (int) $element->formula_id,
                ])->values(),
        ]);
    }

    /**
     * Add a recurring item to a staff member's contract.
     */
    public function addContractElement(Request $request, int $contractId): JsonResponse
    {
        $this->requirePermission('update_contracts');
        $contract = Contract::with('elements')->findOrFail($contractId);

        $validated = $request->validate([
            'element_id' => 'required|integer|exists:sys_hr_payroll_elements,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $element = PayrollElement::findOrFail($validated['element_id']);
        if (!$element->canBeOnContract()) {
            return $this->fail("{$element->element_name} can't be added to a contract. It is a one-time item or one the payroll run calculates itself.");
        }
        if ($contract->elements->contains('element_id', $element->id)) {
            return $this->fail("{$element->element_name} is already on this contract. Edit its amount instead.");
        }

        $before = ContractChange::elementAmounts($contract->elements);
        ContractElement::create([
            'parent_id' => $contract->id,
            'element_id' => $element->id,
            'entry_type_id' => (int) $element->element_type,
            'amount' => $validated['amount'],
        ]);
        $this->logElementChanges($contract, $before);

        return response()->json(['success' => true]);
    }

    /**
     * Change the amount of a recurring item on a contract. It applies to this period and every one after it.
     */
    public function updateContractElement(Request $request, int $rowId): JsonResponse
    {
        $this->requirePermission('update_contracts');
        $row = ContractElement::with('payrollElement')->findOrFail($rowId);
        $contract = Contract::with('elements')->findOrFail($row->parent_id);

        $validated = $request->validate(['amount' => 'required|numeric|min:0']);

        if (!$row->payrollElement?->canBeOnContract()) {
            return $this->fail('This item is not kept on contracts any more. Save the contract to clear it.');
        }

        $before = ContractChange::elementAmounts($contract->elements);
        $row->update(['amount' => $validated['amount']]);
        $this->logElementChanges($contract, $before);

        return response()->json(['success' => true]);
    }

    /**
     * Remove a recurring item from a contract.
     */
    public function removeContractElement(int $rowId): JsonResponse
    {
        $this->requirePermission('update_contracts');
        $row = ContractElement::findOrFail($rowId);
        $contract = Contract::with('elements')->findOrFail($row->parent_id);

        $before = ContractChange::elementAmounts($contract->elements);
        $row->delete();
        $this->logElementChanges($contract, $before);

        return response()->json(['success' => true]);
    }

    /**
     * Log what changed on a contract's recurring items, comparing the amounts held before with those held now.
     */
    private function logElementChanges(Contract $contract, array $before): void
    {
        ContractChange::elements($contract, $before, ContractChange::elementAmounts($contract->elements()->get()), Auth::id());
    }

    /**
     * The contract each staff member in the period is paid on (the latest when there are several), newest first.
     *
     * @return Collection<int, EmployeeContract>
     */
    private function staffInRun(PayrollPeriod $period): Collection
    {
        return EmployeeContract::with('staff')->active()
            ->coveringPeriod($period->start_date->toDateString(), $period->end_date->toDateString())
            ->get()
            ->filter(fn ($contract) => $contract->staff)
            ->sortByDesc(fn ($contract) => [(string) $contract->date_start?->toDateString(), $contract->id])
            ->unique('staff_id')
            ->sortBy(fn ($contract) => $contract->staff->full_names)
            ->values();
    }

    /**
     * A 422 response when the period has already been processed, because the run has used its items.
     */
    private function lockedResponse(PayrollPeriod $period): ?JsonResponse
    {
        return PayrollSummary::where('period_id', $period->id)->exists()
            ? $this->fail('Payroll for this period has already been processed. Reverse or discard that run before changing its one-time items.')
            : null;
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message], 422);
    }

    private function periodLabel(PayrollPeriod $period): string
    {
        return $period->start_date?->format('d M Y') . ' to ' . $period->end_date?->format('d M Y');
    }
}
