<?php

namespace App\Http\Controllers;

use App\Models\DeductionType;
use App\Models\ScheduledDeduction;
use App\Models\Staff;
use App\Services\DeductionSchedule;
use App\Services\ScheduledDeductions;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Scheduled deductions (UAT A6): money a staff member owes that payroll takes a set amount a month until it is paid off.
 * The Payroll menu entry is "Scheduled Deductions", between HELB Loans and Prorated Deductions.
 *
 * Who may do what is a set of permissions, all given to the HR Manager and Directors roles to begin with and changed on the roles screen:
 * read_, create_, update_ (consent, pause, resume, change the monthly amount), close_ (stop early) and manage_deduction_types.
 */
class ScheduledDeductionsController extends Controller
{
    public function __construct(private ScheduledDeductions $deductions)
    {
    }

    private function allow(string $permission): void
    {
        abort_unless(Auth::check() && Auth::user()->isAbleTo($permission), 403, 'You do not have permission to do this with scheduled deductions.');
    }

    private function find(string $encryptedId): ScheduledDeduction
    {
        try {
            $id = Crypt::decrypt($encryptedId);
        } catch (DecryptException) {
            abort(404);
        }

        return ScheduledDeduction::with(['staff', 'type', 'creator'])->findOrFail($id);
    }

    public function index(Request $request)
    {
        $this->allow('read_scheduled_deductions');

        $filter = (string) $request->query('status', 'open');
        $query = ScheduledDeduction::with(['staff', 'type'])->orderByDesc('id');
        if ($filter === 'open') {
            $query->open();
        } elseif (array_key_exists($filter, ScheduledDeduction::STATUS_LABELS)) {
            $query->where('status', $filter);
        } else {
            $filter = 'all';
        }

        return view('deductions.scheduled.index', [
            'deductions' => $query->get(),
            'filter' => $filter,
            'counts' => ScheduledDeduction::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'types' => DeductionType::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $this->allow('create_scheduled_deductions');

        return view('deductions.scheduled.create', [
            'staff' => Staff::where('current_status_id', '!=', 3)->orderBy('full_names')->get(['id', 'staff_number', 'full_names']),
            'types' => DeductionType::active()->orderBy('name')->get(),
        ]);
    }

    /** The month-by-month schedule for what is typed so far, shown before anything is saved. */
    public function preview(Request $request)
    {
        $this->allow('create_scheduled_deductions');

        $plan = $this->planFrom($request);
        $result = DeductionSchedule::build($plan['total'], $plan['monthly'], $plan['start'], $plan['months'], $plan['end']);

        return response()->json([
            'rows' => $result['rows'],
            'months' => $result['months'],
            'monthly' => $result['monthly'],
            'last_amount' => $result['last_amount'],
            'end_month' => $result['rows'] ? end($result['rows'])['label'] : null,
            'total' => $plan['total'],
            'problems' => $result['problems'],
            'notes' => $result['notes'],
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('create_scheduled_deductions');

        $data = $request->validate([
            'staff_id' => 'required|integer|exists:sys_staff,id',
            'type_id' => 'required|integer|exists:sys_hr_deduction_types,id',
            'description' => 'nullable|string|max:255',
            'total_amount' => 'required|numeric|min:0.01|max:99999999',
            'plan_by' => 'required|in:monthly,months,end',
            'monthly_amount' => 'required_if:plan_by,monthly|nullable|numeric|min:0.01|max:99999999',
            'months' => 'required_if:plan_by,months|nullable|integer|min:1|max:' . DeductionSchedule::MAX_MONTHS,
            'end_month' => 'required_if:plan_by,end|nullable|date_format:Y-m',
            'start_month' => 'required|date_format:Y-m',
            'consent' => 'nullable|file|mimes:pdf|max:5120',
        ], [
            'monthly_amount.required_if' => 'Enter the amount to take each month.',
            'months.required_if' => 'Enter the number of months.',
            'end_month.required_if' => 'Choose the month it should finish by.',
            'consent.mimes' => 'The signed consent form must be a PDF.',
            'consent.max' => 'The signed consent form is too big (the limit is 5 MB).',
        ]);

        // Only the one the plan is built from counts; the others are worked out from it
        $by = $data['plan_by'];
        $deduction = $this->deductions->create([
            'staff_id' => $data['staff_id'],
            'type_id' => $data['type_id'],
            'description' => $data['description'] ?? null,
            'total_amount' => $data['total_amount'],
            'monthly_amount' => $by === 'monthly' ? $data['monthly_amount'] : null,
            'months' => $by === 'months' ? $data['months'] : null,
            'end_month' => $by === 'end' ? $data['end_month'] : null,
            'start_month' => $data['start_month'],
        ], $request->file('consent'), Auth::id());

        $message = $deduction->status === ScheduledDeduction::ACTIVE
            ? 'The deduction is saved and will be taken from the ' . $deduction->start_month->format('F Y') . ' payroll.'
            : 'The deduction is saved and is on hold: nothing is taken until the signed consent form is added.';

        return redirect()->route('scheduled-deductions.show', Crypt::encrypt($deduction->id))->with('success', $message);
    }

    public function show(string $encryptedId)
    {
        $this->allow('read_scheduled_deductions');
        $deduction = $this->find($encryptedId);

        return view('deductions.scheduled.show', [
            'deduction' => $deduction,
            'schedule' => $this->deductions->schedule($deduction),
            'events' => $deduction->events()->with('user')->orderByDesc('id')->get(),
            'encryptedId' => $encryptedId,
        ]);
    }

    public function storeConsent(Request $request, string $encryptedId)
    {
        $this->allow('update_scheduled_deductions');
        $deduction = $this->find($encryptedId);
        $request->validate(['consent' => 'required|file|mimes:pdf|max:5120'], [
            'consent.required' => 'Choose the signed consent form (a PDF).',
            'consent.mimes' => 'The signed consent form must be a PDF.',
            'consent.max' => 'The signed consent form is too big (the limit is 5 MB).',
        ]);

        $wasOnHold = $deduction->status === ScheduledDeduction::ON_HOLD;
        $this->deductions->addConsent($deduction, $request->file('consent'), Auth::id());

        return back()->with('success', $wasOnHold ? 'The consent form is saved and the deduction is now active.' : 'The consent form is saved.');
    }

    public function downloadConsent(string $encryptedId)
    {
        $this->allow('read_scheduled_deductions');
        $deduction = $this->find($encryptedId);
        abort_unless($deduction->hasConsent() && Storage::disk(ScheduledDeductions::DISK)->exists($deduction->consent_path), 404);

        return Storage::disk(ScheduledDeductions::DISK)->response($deduction->consent_path, $deduction->consent_name ?: 'consent.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function pause(Request $request, string $encryptedId)
    {
        $this->allow('update_scheduled_deductions');
        $deduction = $this->find($encryptedId);
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $this->deductions->pause($deduction, $data['reason'], Auth::id());

        return back()->with('success', 'The deduction is paused. Nothing is taken until it is resumed.');
    }

    public function resume(string $encryptedId)
    {
        $this->allow('update_scheduled_deductions');
        $deduction = $this->find($encryptedId);

        $this->deductions->resume($deduction, Auth::id());

        return back()->with('success', $deduction->fresh()->status === ScheduledDeduction::ACTIVE ? 'The deduction is active again.' : 'There is no signed consent form, so the deduction is on hold.');
    }

    public function close(Request $request, string $encryptedId)
    {
        $this->allow('close_scheduled_deductions');
        $deduction = $this->find($encryptedId);
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $this->deductions->close($deduction, $data['reason'], Auth::id());

        return back()->with('success', 'The deduction is closed. Nothing more will be taken.');
    }

    public function changeMonthly(Request $request, string $encryptedId)
    {
        $this->allow('update_scheduled_deductions');
        $deduction = $this->find($encryptedId);
        $data = $request->validate([
            'monthly_amount' => 'required|numeric|min:0.01|max:99999999',
            'reason' => 'required|string|max:1000',
        ], ['monthly_amount.required' => 'Enter the new monthly amount.']);

        $this->deductions->changeMonthly($deduction, (float) $data['monthly_amount'], $data['reason'], Auth::id());

        return back()->with('success', 'The monthly amount is changed.');
    }

    public function storeType(Request $request)
    {
        $this->allow('manage_deduction_types');
        $data = $request->validate([
            // HELB is deducted by law and needs no consent, so it is not a scheduled deduction: it has its own HELB Loans screen
            'name' => ['required', 'string', 'max:100', 'unique:sys_hr_deduction_types,name', 'not_regex:/helb/i'],
            'is_company_loan' => 'nullable|boolean',
        ], [
            'name.unique' => 'There is already a type with that name.',
            'name.not_regex' => 'HELB is deducted by law and has its own screen: use HELB Loans.',
        ]);

        DeductionType::create([
            'name' => trim($data['name']),
            'is_company_loan' => (bool) ($data['is_company_loan'] ?? false),
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'The type "' . trim($data['name']) . '" is added. You can choose it when adding a deduction.');
    }

    /** Hide a type from the list when adding a deduction (or show it again). Deductions already made with it are not touched. */
    public function toggleType(DeductionType $type)
    {
        $this->allow('manage_deduction_types');
        $type->is_active = !$type->is_active;
        $type->save();

        return back()->with('success', 'The type "' . $type->name . '" is now ' . ($type->is_active ? 'shown' : 'hidden') . '.');
    }

    /** @return array{total: float, monthly: ?float, months: ?int, end: ?string, start: string} */
    private function planFrom(Request $request): array
    {
        $by = $request->input('plan_by', 'monthly');
        $start = (string) $request->input('start_month');
        $start = preg_match('/^\d{4}-\d{2}$/', $start) ? $start . '-01' : now()->startOfMonth()->toDateString();

        return [
            'total' => (float) $request->input('total_amount'),
            'monthly' => $by === 'monthly' && $request->filled('monthly_amount') ? (float) $request->input('monthly_amount') : null,
            'months' => $by === 'months' && $request->filled('months') ? (int) $request->input('months') : null,
            'end' => $by === 'end' && $request->filled('end_month') ? (string) $request->input('end_month') : null,
            'start' => $start,
        ];
    }
}
