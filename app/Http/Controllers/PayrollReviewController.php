<?php

namespace App\Http\Controllers;

use App\Exports\GenericReportExport;
use App\Models\EmployeeContract;
use App\Models\HelbDeduction;
use App\Models\HelbDeductionRepeat;
use App\Models\Notification as NotificationModel;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSummary;
use App\Models\ProratedDeduction;
use App\Models\ProratedDeductionRepeat;
use App\Models\Staff;
use App\Models\TaxBand;
use App\Models\User;
use App\Services\ErrorDispatcher;
use App\Services\PayrollMonthOpener;
use App\Services\ScheduledDeductions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class PayrollReviewController extends Controller
{
    /**
     * The alerts that point at employees in the run, and the flag each puts on their row. The row flags, the counts and the
     * review page's alert filter all come from this one list, so what is flagged is always what the filter shows.
     */
    public const ROW_ALERTS = [
        'negative_net' => 'Negative pay',
        'no_bank' => 'No bank details',
        'multiple_contracts' => 'Several contracts',
    ];

    /**
     * Stop the request with a 403 unless the signed-in user holds the given payroll permission.
     */
    private function requirePayrollPermission(string $permission): void
    {
        abort_unless(Auth::check() && Auth::user()->isAbleTo($permission), 403, 'You do not have permission to perform this payroll action.');
    }

    /** Review page: run totals, warnings, the per-employee list and history. */
    public function show($periodId)
    {
        $this->requirePayrollPermission('read_payroll_processing');

        $period = PayrollPeriod::findOrFail($periodId);
        $run = PayrollRun::with('logs.user')->where('period_id', $period->id)->firstOrFail();
        $user = Auth::user();
        $canProcess = $user->isAbleTo('create_payroll_processing');
        $canApprove = $user->isAbleTo('approve_payroll_processing');
        $isEditable = in_array($run->status, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_RETURNED], true);

        return view('payroll.review', [
            'period' => $period,
            'run' => $run,
            'totals' => $this->runTotals($period->id),
            // Notes from processing (shown once), and the alerts worked out from the run, some of which can filter the grid
            'warnings' => session()->pull('payroll_process_warnings.' . $period->id, []),
            'alerts' => $this->runAlerts($period->id),
            'alertLabels' => self::ROW_ALERTS,
            'userNames' => User::whereIn('id', array_filter([$run->processed_by, $run->submitted_by, $run->approved_by]))->pluck('name', 'id'),
            'canSubmit' => $canProcess && $isEditable,
            'canDiscard' => $canProcess && $isEditable,
            'canDecide' => $canApprove && $run->status === PayrollRun::STATUS_WITH_ACCOUNTANT && !$this->isOwnRun($run),
            'isOwnRun' => $canApprove && $this->isOwnRun($run),
            'canReverseApproved' => $canApprove && $run->status === PayrollRun::STATUS_APPROVED,
            'canDownloadEft' => $canApprove && in_array($run->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID], true),
            // Paying is the accountant's call, and never the person who processed or submitted the run
            'canMarkPaid' => $canApprove && $run->status === PayrollRun::STATUS_APPROVED && !$this->isOwnRun($run),
            'payment' => $run->status === PayrollRun::STATUS_APPROVED ? $this->paymentSummary($run) : null,
            'reverseNote' => $run->status === PayrollRun::STATUS_APPROVED ? $this->reverseNote($run) : null,
            'paidByName' => $run->paid_by ? User::whereKey($run->paid_by)->value('name') : null,
            // What changed since the last payroll, as it stood when HR processed this run (none for runs from before this was kept)
            'changes' => $run->changes_snapshot,
        ]);
    }

    /**
     * Per-employee payroll list for the review table. ?alert_filter=negative_net|no_bank|multiple_contracts keeps only the
     * employees with that alert. alert_counts always covers the whole run, so the page can show how many are left to fix.
     */
    public function employees(Request $request, $periodId)
    {
        $this->requirePayrollPermission('read_payroll_processing');

        $filter = $request->query('alert_filter');
        if (filled($filter) && (!is_string($filter) || !array_key_exists($filter, self::ROW_ALERTS))) {
            return response()->json(['error' => 'Unknown alert filter.'], 422);
        }

        $all = $this->employeeRows((int) $periodId);

        return response()->json([
            'employees' => filled($filter) ? $all->filter(fn ($row) => in_array($filter, $row['alerts'], true))->values() : $all,
            'alert_counts' => $this->alertCounts($all),
        ]);
    }

    /** HR sends a draft or returned run to the accountant. */
    public function submit($periodId)
    {
        $this->requirePayrollPermission('create_payroll_processing');

        return $this->transition($periodId, function (PayrollRun $run) {
            $this->requireStatus($run, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_RETURNED], 'Only a draft or returned run can be submitted.');

            $run->update([
                'status' => PayrollRun::STATUS_WITH_ACCOUNTANT,
                'submitted_by' => Auth::id(),
                'submitted_at' => now(),
                'return_comment' => null,
            ]);
            $run->record('submitted', Auth::id());
            $this->notifyAccountants($run);

            return 'Payroll submitted to the accountant.';
        });
    }

    /** The accountant approves: payslips open and loan balances update. */
    public function approve($periodId)
    {
        $this->requirePayrollPermission('approve_payroll_processing');

        return $this->transition($periodId, function (PayrollRun $run) {
            $this->requireStatus($run, [PayrollRun::STATUS_WITH_ACCOUNTANT], 'This payroll run is not awaiting approval.');
            $this->rejectOwnRun($run);

            PayrollSummary::where('period_id', $run->period_id)
                ->where('current_status_id', 1)
                ->update(['current_status_id' => 5, 'approved_by' => Auth::id(), 'approved_at' => now()]);
            $this->applyLoanDeductions($run);

            $run->update([
                'status' => PayrollRun::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
            $run->record('approved', Auth::id());

            return 'Payroll approved. Payslips are now available.';
        });
    }

    /** The accountant sends a run back to HR with a reason. */
    public function sendBack(Request $request, $periodId)
    {
        $this->requirePayrollPermission('approve_payroll_processing');
        $validated = $request->validate(['comment' => 'required|string|max:1000']);

        return $this->transition($periodId, function (PayrollRun $run) use ($validated) {
            $this->requireStatus($run, [PayrollRun::STATUS_WITH_ACCOUNTANT], 'This payroll run is not awaiting approval.');
            $this->rejectOwnRun($run);

            $run->update([
                'status' => PayrollRun::STATUS_RETURNED,
                'return_comment' => $validated['comment'],
            ]);
            $run->record('returned', Auth::id(), $validated['comment']);
            $this->notifyUser($run, $run->submitted_by, 'Payroll returned', 'The accountant returned this payroll run: ' . $validated['comment']);

            return 'Payroll returned to HR.';
        });
    }

    /**
     * Remove a run's results so the period can be processed again. HR can
     * discard an unapproved run; only an approver can reverse an approved one.
     */
    public function reverse(Request $request, $periodId)
    {
        $validated = $request->validate(['comment' => 'required|string|max:1000']);

        return $this->transition($periodId, function (PayrollRun $run) use ($validated) {
            // Money has gone out once a run is paid, so nothing undoes it from here. Mistakes are corrected in the next month.
            if ($run->status === PayrollRun::STATUS_PAID) {
                throw new \DomainException('This payroll has been marked as paid, so it can no longer be reversed. Correct any mistake with a one-time item in the next payroll month.');
            }

            $wasApproved = $run->status === PayrollRun::STATUS_APPROVED;
            $this->requirePayrollPermission($wasApproved ? 'approve_payroll_processing' : 'create_payroll_processing');
            $this->requireStatus($run, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_RETURNED, PayrollRun::STATUS_APPROVED], 'A run with the accountant must be returned before it can be reversed.');

            if ($wasApproved) {
                // Loan balances roll forward month by month, so an earlier month cannot be taken back under a later one.
                if ($this->laterMonthHasResults($run)) {
                    throw new \DomainException('Payroll for a later month has already been processed. Reverse that month first.');
                }
                $this->rollBackLoanDeductions($run);
            }
            PayrollSummary::where('period_id', $run->period_id)->delete();

            $run->update([
                'status' => PayrollRun::STATUS_REVERSED,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'return_comment' => null,
            ]);
            $run->record('reversed', Auth::id(), $validated['comment']);

            // The people who prepared an approved run are told it was taken back, and why
            if ($wasApproved) {
                $label = $run->period?->start_date?->format('F Y') ?? 'a payroll month';
                foreach (array_unique(array_filter([$run->processed_by, $run->submitted_by])) as $userId) {
                    if ((int) $userId !== (int) Auth::id()) {
                        $this->notifyUser($run, (int) $userId, 'Approved payroll reversed', "The approved payroll for {$label} was reversed: {$validated['comment']} Payslips were withdrawn and the month can be processed again.");
                    }
                }
            }

            return 'Payroll reversed. The period can now be processed again.';
        });
    }

    /**
     * The accountant confirms the bank has paid this approved run. That locks the run for good and opens the next payroll
     * month for everyone who works on payroll.
     */
    public function markPaid(Request $request, $periodId)
    {
        $this->requirePayrollPermission('approve_payroll_processing');

        $validated = $request->validate([
            'payment_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'payment_reference' => 'required|string|max:100',
            'payment_note' => 'nullable|string|max:1000',
            'confirmed' => 'accepted',
            'net_total' => 'required|numeric',
            'employees' => 'required|integer',
        ], [
            'confirmed.accepted' => 'Tick the box to confirm the bank has processed this payment.',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
            'payment_reference.required' => 'Enter the bank reference for this payment.',
        ]);

        return $this->transition($periodId, function (PayrollRun $run) use ($validated) {
            $this->requireStatus($run, [PayrollRun::STATUS_APPROVED], $run->status === PayrollRun::STATUS_PAID
                ? 'This payroll has already been marked as paid.'
                : 'Only an approved payroll run can be marked as paid.');
            $this->rejectOwnRun($run);

            // What the accountant confirmed on screen must still be what is in the run, in case it was reversed and re-run meanwhile
            $totals = $this->runTotals((int) $run->period_id);
            if ((int) $validated['employees'] !== (int) $totals->employees || abs((float) $validated['net_total'] - (float) $totals->net_pay) > 0.005) {
                throw new \DomainException('This payroll has changed since you opened the page. Reload it and check the totals again before confirming payment.');
            }

            $run->update([
                'status' => PayrollRun::STATUS_PAID,
                'paid_by' => Auth::id(),
                'paid_at' => now(),
                'payment_date' => $validated['payment_date'],
                'payment_reference' => $validated['payment_reference'],
                'payment_note' => $validated['payment_note'] ?? null,
            ]);
            $detail = 'Paid on ' . \Carbon\Carbon::parse($validated['payment_date'])->format('d M Y') . '. Bank reference: ' . $validated['payment_reference'] . '.'
                . (filled($validated['payment_note'] ?? null) ? ' ' . $validated['payment_note'] : '');
            $run->record('paid', Auth::id(), $detail);

            $period = PayrollPeriod::findOrFail($run->period_id);
            $paidLabel = $period->start_date->format('F Y');
            [$opened, $failed] = $this->openNextMonth($run, $period);

            $paidBy = Auth::user()->name;
            if ($opened) {
                $range = \Carbon\Carbon::parse($opened['start'])->format('d M Y') . ' to ' . \Carbon\Carbon::parse($opened['end'])->format('d M Y');
                $this->notifyPayrollTeam($run, 'New payroll month open', "{$paidLabel} payroll was marked as paid by {$paidBy}. {$opened['label']} payroll is now open ({$range}) and can be processed.", route('payroll.processing'));

                return "{$paidLabel} payroll marked as paid. {$opened['label']} payroll is now open.";
            }

            if ($failed) {
                $this->notifyPayrollTeam($run, 'Payroll marked as paid', "{$paidLabel} payroll was marked as paid by {$paidBy}. The next month could not be opened automatically, so please add it on the Payroll Calendar.", route('settings.hr.payroll.calendar'));

                return "{$paidLabel} payroll marked as paid. The next month could not be opened automatically, so add it on the Payroll Calendar.";
            }

            $this->notifyPayrollTeam($run, 'Payroll marked as paid', "{$paidLabel} payroll was marked as paid by {$paidBy}.", route('payroll.review', $run->period_id));

            return "{$paidLabel} payroll marked as paid.";
        });
    }

    /**
     * Open the month after a paid one. The payment is already confirmed, so a failure here must not undo it: it is logged and
     * HR is told to add the month on the Payroll Calendar.
     *
     * @return array{0: array{label: string, start: string, end: string}|null, 1: bool} the month that was opened (null when none was needed) and whether opening failed
     */
    private function openNextMonth(PayrollRun $run, PayrollPeriod $period): array
    {
        try {
            $opened = app(PayrollMonthOpener::class)->openAfter($period, Auth::id());
        } catch (\Throwable $e) {
            Log::error('Could not open the next payroll month after period ' . $period->id . ': ' . $e->getMessage());
            $run->record('month_not_opened', Auth::id(), 'The next payroll month could not be opened automatically. Add it on the Payroll Calendar.');

            return [null, true];
        }

        if ($opened) {
            $run->record('month_opened', Auth::id(), "{$opened['label']} payroll is open: {$opened['start']} to {$opened['end']}.");
        }

        return [$opened, false];
    }

    /**
     * Whether any later payroll month already has results. Used to stop an earlier month being reversed underneath it.
     */
    private function laterMonthHasResults(PayrollRun $run): bool
    {
        $start = $run->period?->start_date;
        if (!$start) {
            return false;
        }

        return PayrollSummary::whereIn('period_id', PayrollPeriod::where('start_date', '>', $start->toDateString())->select('id'))->exists();
    }

    /**
     * Everything the accountant should see before confirming payment: what the run adds up to, who is and is not in the
     * EFT file, and whether a file was downloaded.
     */
    private function paymentSummary(PayrollRun $run): array
    {
        $payable = ['count' => 0, 'total' => 0.0];
        $excluded = ['count' => 0, 'total' => 0.0, 'reasons' => [], 'people' => []];

        foreach (PayrollSummary::with('staff.bank')->where('period_id', $run->period_id)->get() as $summary) {
            $reason = $this->eftExclusionReason($summary);
            if (!$reason) {
                $payable['count']++;
                $payable['total'] += (float) $summary->net_pay;
                continue;
            }
            $excluded['count']++;
            $excluded['total'] += (float) $summary->net_pay;
            $excluded['reasons'][$reason] = ($excluded['reasons'][$reason] ?? 0) + 1;
            if (count($excluded['people']) < 8) {
                $excluded['people'][] = ($summary->staff?->full_names ?? 'Staff #' . $summary->staff_id) . ' (' . $reason . ')';
            }
        }

        $totals = $this->runTotals((int) $run->period_id);
        $download = $run->logs->firstWhere('action', 'eft_test_file');

        return [
            'employees' => (int) $totals->employees,
            'net_total' => round((float) $totals->net_pay, 2),
            'payable' => ['count' => $payable['count'], 'total' => round($payable['total'], 2)],
            'excluded' => ['count' => $excluded['count'], 'total' => round($excluded['total'], 2), 'reasons' => $excluded['reasons'], 'people' => $excluded['people']],
            'eft_downloaded' => $download ? ['by' => $download->user?->name ?? 'Unknown', 'at' => $download->created_at?->format('d M Y, H:i')] : null,
            'default_date' => now()->toDateString(),
        ];
    }

    /**
     * What the Reverse dialog should warn about: the payslips being withdrawn and, when an EFT file was already downloaded,
     * who took it and when, because a reversal cannot recall a file the bank may have processed.
     */
    private function reverseNote(PayrollRun $run): string
    {
        $count = PayrollSummary::where('period_id', $run->period_id)->count();
        $note = "Payslips for {$count} staff will be withdrawn, loan balances put back and the month opened again for processing.";
        $download = $run->logs->firstWhere('action', 'eft_test_file');
        if ($download) {
            $note .= ' An EFT file was downloaded by ' . ($download->user?->name ?? 'someone') . ' on ' . $download->created_at?->format('d M Y, H:i') . '. Make sure the bank has not processed it before you reverse.';
        }

        return $note;
    }

    /** Excel copy of the per-employee list, available at any stage of the run. */
    public function exportRegister($periodId)
    {
        $this->requirePayrollPermission('read_payroll_processing');

        $period = PayrollPeriod::findOrFail($periodId);
        $run = PayrollRun::where('period_id', $period->id)->firstOrFail();
        $employees = $this->employeeRows($period->id);
        $amountKeys = ['basic', 'gross', 'paye', 'nssf', 'sha', 'housing_levy', 'other_deductions', 'total_deductions', 'net_pay'];

        $rows = $employees->values()->map(fn ($row, $index) => array_merge(
            [$index + 1, $row['staff_number'], $row['name'], $row['department']],
            array_map(fn ($key) => number_format($row[$key], 2), $amountKeys)
        ))->all();
        $rows[] = array_merge(['', 'TOTALS', '', ''], array_map(fn ($key) => number_format($employees->sum($key), 2), $amountKeys));

        $label = $period->start_date->format('F Y');

        return Excel::download(
            GenericReportExport::build(
                title: 'Payroll Review — ' . $label,
                headings: ['#', 'Staff No', 'Name', 'Department', 'Basic', 'Gross', 'PAYE', 'NSSF', 'SHA', 'Housing Levy', 'Other Deductions', 'Total Deductions', 'Net Pay'],
                dataRows: $rows,
                meta: ['Status: ' . $run->status_label, 'Generated: ' . now()->format('d/m/Y H:i:s')],
                colWidths: ['A' => 5, 'B' => 13, 'C' => 26, 'D' => 20, 'E' => 14, 'F' => 14, 'G' => 13, 'H' => 12, 'I' => 12, 'J' => 13, 'K' => 16, 'L' => 16, 'M' => 14],
                hasTotals: true,
            ),
            'payroll-review-' . $period->start_date->format('Y-m') . '.xlsx'
        );
    }

    /**
     * Sample EFT file in a generic layout. It is not a bank format: the real
     * layout replaces this once the company's bank specification is available.
     */
    public function eftTestFile($periodId)
    {
        $this->requirePayrollPermission('approve_payroll_processing');

        $period = PayrollPeriod::findOrFail($periodId);
        $run = PayrollRun::where('period_id', $period->id)->firstOrFail();
        abort_unless(in_array($run->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID], true), 422, 'Only an approved payroll run can produce an EFT file.');

        $summaries = PayrollSummary::with('staff.bank')->where('period_id', $period->id)->get();
        $reference = 'PAY-' . $period->start_date->format('Ym');
        $payable = [];
        $excluded = [];

        foreach ($summaries as $summary) {
            $reason = $this->eftExclusionReason($summary);
            if ($reason) {
                $excluded[] = [$summary->staff?->staff_number ?? $summary->staff_number_id, $summary->staff?->full_names ?? '-', $reason];
                continue;
            }
            $payable[] = [
                $summary->staff->full_names,
                $summary->staff->bank?->bank_name,
                '',
                $summary->staff->account_number,
                number_format((float) $summary->net_pay, 2, '.', ''),
                $reference . '-' . $summary->staff->staff_number,
            ];
        }

        $run->record('eft_test_file', Auth::id());
        $total = number_format(array_sum(array_map(fn ($row) => (float) $row[4], $payable)), 2, '.', '');

        return response()->streamDownload(function () use ($payable, $excluded, $total, $reference) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['TEST - NOT FOR BANK']);
            fputcsv($out, ['Batch reference', $reference]);
            fputcsv($out, ['Debit account', 'TEST ACCOUNT']);
            fputcsv($out, ['Beneficiary', 'Bank', 'Branch code', 'Account number', 'Amount', 'Reference']);
            foreach ($payable as $row) {
                fputcsv($out, $row);
            }
            fputcsv($out, ['TOTAL', count($payable) . ' payments', '', '', $total, '']);
            if ($excluded) {
                fputcsv($out, []);
                fputcsv($out, ['EXCLUDED - NOT PAID IN THIS FILE']);
                fputcsv($out, ['Staff no', 'Name', 'Reason']);
                foreach ($excluded as $row) {
                    fputcsv($out, $row);
                }
            }
            fclose($out);
        }, 'TEST-NOT-FOR-BANK-payroll-' . $period->start_date->format('Y-m') . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Why a payslip cannot be paid in the EFT file, or null when it can.
     */
    private function eftExclusionReason(PayrollSummary $summary): ?string
    {
        if (!$summary->staff) {
            return 'Staff record not found';
        }
        if ((float) $summary->net_pay <= 0) {
            return 'Net pay is zero or negative';
        }
        if (blank($summary->staff->account_number) || !$summary->staff->bank) {
            return 'Missing bank details';
        }

        return null;
    }

    /** Run a status change under a row lock, reporting rule failures as 422. */
    private function transition($periodId, callable $change)
    {
        try {
            $message = DB::transaction(function () use ($periodId, $change) {
                return $change(PayrollRun::where('period_id', $periodId)->lockForUpdate()->firstOrFail());
            });

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'This period has no payroll run.'], 404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            try {
                ErrorDispatcher::dispatch($e);
            } catch (\Throwable $ex) {
                Log::error('Failed dispatching error via ErrorDispatcher: ' . $ex->getMessage());
            }
            Log::error('Error updating payroll run: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Failed to update the payroll run'], 500);
        }
    }

    /**
     * Throw a DomainException (reported as 422) unless the run is in one of the given statuses.
     */
    private function requireStatus(PayrollRun $run, array $statuses, string $message): void
    {
        if (!in_array($run->status, $statuses, true)) {
            throw new \DomainException($message);
        }
    }

    /**
     * Whether the signed-in user processed or submitted this run.
     */
    private function isOwnRun(PayrollRun $run): bool
    {
        return in_array(Auth::id(), [$run->processed_by, $run->submitted_by], false);
    }

    /**
     * Maker-checker: throw a DomainException when the user is reviewing a run they processed or submitted.
     */
    private function rejectOwnRun(PayrollRun $run): void
    {
        if ($this->isOwnRun($run)) {
            throw new \DomainException('You processed or submitted this payroll, so another accountant must review it.');
        }
    }

    /**
     * Headcount and summed earnings, statutory deductions and net pay for a period's run.
     */
    private function runTotals(int $periodId): object
    {
        return PayrollSummary::where('period_id', $periodId)
            ->selectRaw('COUNT(*) as employees, COALESCE(SUM(basic_salary), 0) as basic, COALESCE(SUM(total_earnings), 0) as gross,
                COALESCE(SUM(tax_payable), 0) as paye, COALESCE(SUM(nssf), 0) as nssf, COALESCE(SUM(nhif), 0) as sha,
                COALESCE(SUM(housing_levy), 0) as housing_levy, COALESCE(SUM(total_deductions), 0) as total_deductions,
                COALESCE(SUM(net_pay), 0) as net_pay')
            ->toBase()
            ->first();
    }

    /**
     * Issues the reviewer should see before the run is approved. Each says what it is about: 'filter' names the grid filter that
     * shows the employees concerned, 'staff' lists people who are not in the run at all, and 'link' points to where it is fixed.
     */
    private function runAlerts(int $periodId): array
    {
        $multiple = $this->multipleContractStaffIds();
        $counts = array_fill_keys(array_keys(self::ROW_ALERTS), 0);
        foreach (PayrollSummary::with('staff.bank')->where('period_id', $periodId)->get() as $summary) {
            foreach ($this->rowAlertKeys($summary, $multiple) as $key) {
                $counts[$key]++;
            }
        }

        $alerts = [];
        if ($counts['negative_net']) {
            $alerts[] = ['key' => 'negative_net', 'tone' => 'danger', 'count' => $counts['negative_net'], 'filter' => 'negative_net',
                'message' => "{$counts['negative_net']} employee(s) have negative net pay."];
        }
        if ($counts['no_bank']) {
            $alerts[] = ['key' => 'no_bank', 'tone' => 'danger', 'count' => $counts['no_bank'], 'filter' => 'no_bank',
                'message' => "{$counts['no_bank']} employee(s) have no bank details and cannot be included in the EFT file."];
        }

        $contractStaffIds = EmployeeContract::active()->whereNotNull('staff_id')->pluck('staff_id');
        $withoutContract = Staff::where('current_status_id', 1)->whereNotIn('id', $contractStaffIds)->orderBy('full_names')->get(['id', 'staff_number', 'full_names']);
        if ($withoutContract->isNotEmpty()) {
            $alerts[] = ['key' => 'no_contract', 'tone' => 'warning', 'count' => $withoutContract->count(), 'filter' => null,
                'message' => "{$withoutContract->count()} active staff have no active contract and are not in this run.",
                'staff' => $withoutContract->take(100)->map(fn ($staff) => ['number' => $staff->staff_number, 'name' => $staff->full_names])->values()->all(),
                'more' => max(0, $withoutContract->count() - 100)];
        }

        if ($counts['multiple_contracts']) {
            $alerts[] = ['key' => 'multiple_contracts', 'tone' => 'warning', 'count' => $counts['multiple_contracts'], 'filter' => 'multiple_contracts',
                'message' => "{$counts['multiple_contracts']} staff have more than one active contract. Only the latest contract was paid."];
        }

        if ($this->taxBandsOverlap()) {
            $alerts[] = ['key' => 'tax_bands', 'tone' => 'danger', 'count' => 1, 'filter' => null,
                'message' => 'Tax bands overlap or continue after the last band, so PAYE may be wrong. Check Tax Bands.',
                'link' => ['label' => 'Open Tax Bands', 'url' => route('payroll.tax_bands')]];
        }

        return $alerts;
    }

    /** Which of the row alerts apply to one payroll summary. */
    private function rowAlertKeys(PayrollSummary $summary, Collection $multipleContractStaff): array
    {
        $staff = $summary->staff;
        $keys = [];

        if ((float) $summary->net_pay < 0) {
            $keys[] = 'negative_net';
        }
        if (!$staff || blank($staff->account_number) || !$staff->bank) {
            $keys[] = 'no_bank';
        }
        if ($staff && $multipleContractStaff->contains($staff->id)) {
            $keys[] = 'multiple_contracts';
        }

        return $keys;
    }

    /** Ids of the staff who hold more than one active contract. */
    private function multipleContractStaffIds(): Collection
    {
        return EmployeeContract::active()->whereNotNull('staff_id')->pluck('staff_id')->countBy()->filter(fn ($count) => $count > 1)->keys();
    }

    /** How many of the given rows carry each row alert. */
    private function alertCounts(Collection $rows): array
    {
        return collect(self::ROW_ALERTS)->map(fn ($label, $key) => $rows->filter(fn ($row) => in_array($key, $row['alerts'], true))->count())->all();
    }

    /**
     * Whether the tax bands overlap or continue after the last band, which would make PAYE wrong.
     */
    private function taxBandsOverlap(): bool
    {
        $previousTo = null;
        $reachedLast = false;

        foreach (TaxBand::orderBy('range_from')->get() as $band) {
            if ($reachedLast || ($previousTo !== null && (float) $band->range_from <= $previousTo)) {
                return true;
            }
            $reachedLast = (bool) $band->is_last;
            $previousTo = $band->range_to === null ? null : (float) $band->range_to;
        }

        return false;
    }

    /**
     * One row per employee in the run, sorted by name, with alerts and the change from the previous approved payroll.
     * edit_url opens the staff record, for people who may edit staff.
     */
    private function employeeRows(int $periodId): Collection
    {
        $summaries = PayrollSummary::with(['staff.bank', 'staff.department'])
            ->where('period_id', $periodId)
            ->get();
        $previousNet = $this->previousNetPay($summaries->first()?->period);
        $multiple = $this->multipleContractStaffIds();
        $canEditStaff = (bool) Auth::user()?->isAbleTo('update_staff');

        return $summaries->map(function (PayrollSummary $summary) use ($previousNet, $multiple, $canEditStaff) {
            $staff = $summary->staff;
            $statutory = (float) $summary->tax_payable + (float) $summary->nssf + (float) $summary->nhif + (float) $summary->housing_levy;
            $previous = $previousNet->get($summary->staff_id);
            $alerts = $this->rowAlertKeys($summary, $multiple);

            return [
                'id' => $summary->id,
                'staff_id' => $staff?->id,
                'staff_number' => $staff?->staff_number ?? $summary->staff_number_id ?? '-',
                'name' => $staff?->full_names ?? 'Unknown',
                'department' => $staff?->department?->department_name ?? '-',
                'basic' => (float) $summary->basic_salary,
                'gross' => (float) $summary->total_earnings,
                'paye' => (float) $summary->tax_payable,
                'nssf' => (float) $summary->nssf,
                'sha' => (float) $summary->nhif,
                'housing_levy' => (float) $summary->housing_levy,
                'other_deductions' => round((float) $summary->total_deductions - $statutory, 2),
                'total_deductions' => (float) $summary->total_deductions,
                'net_pay' => (float) $summary->net_pay,
                'change' => $previous === null ? null : round((float) $summary->net_pay - (float) $previous, 2),
                'alerts' => $alerts,
                'flags' => array_map(fn ($key) => self::ROW_ALERTS[$key], $alerts),
                'edit_url' => $staff && $canEditStaff ? route('staff.edit', Crypt::encrypt($staff->id)) : null,
            ];
        })->sortBy('name')->values();
    }

    /** Net pay per staff member from the most recent earlier approved payroll. */
    private function previousNetPay($period): Collection
    {
        if (!$period) {
            return collect();
        }

        $previousPeriod = PayrollSummary::where('period', '<', $period)
            ->where('current_status_id', 5)
            ->max('period');

        return $previousPeriod
            ? PayrollSummary::where('period', $previousPeriod)->where('current_status_id', 5)->pluck('net_pay', 'staff_id')
            : collect();
    }

    /** Post the run's repayments to the HELB and prorated loan records. */
    private function applyLoanDeductions(PayrollRun $run): void
    {
        foreach ($this->runLoanDeductions($run) as $deductions) {
            if (!empty($deductions['helb'])) {
                $info = $deductions['helb'];
                $loan = HelbDeduction::find($info['parent_id']);
                if ($loan) {
                    $balance = max(0, (float) $loan->loan_balance - (float) $info['deduction_amount']);
                    HelbDeductionRepeat::create([
                        'parent_id' => $loan->id,
                        'deduction_date' => $info['deduction_date'],
                        'amount' => $info['deduction_amount'],
                        'loan_balance' => $balance,
                        'current_status_id' => 5,
                        'period_id' => $run->period_id,
                    ]);
                    $loan->loan_balance = $balance;
                    $loan->cleared = $balance <= 0 ? 1 : $loan->cleared;
                    $loan->save();
                }
            }

            foreach ($deductions['prorated'] ?? [] as $info) {
                $loan = ProratedDeduction::find($info['parent_id']);
                if (!$loan) {
                    continue;
                }

                $amount = (float) $info['deduction_amount'];
                $repaid = (float) ProratedDeductionRepeat::where('parent_id', $loan->id)->sum('total_amount');
                $balance = (float) $loan->total_amount - (float) ($loan->initial_deduction ?? 0) - $repaid - $amount;
                $principalShare = (float) $loan->total_amount > 0 ? (float) $loan->principal_amount / (float) $loan->total_amount : 1;

                ProratedDeductionRepeat::create([
                    'parent_id' => $loan->id,
                    'deduction_date' => $info['deduction_date'],
                    'amount' => round($amount * $principalShare, 2),
                    'interest_amount' => round($amount * (1 - $principalShare), 2),
                    'total_amount' => $amount,
                    'amount_balance' => max(0, $balance),
                    'current_status_id' => 5,
                    'period_id' => $run->period_id,
                ]);

                if ($balance <= 0) {
                    $loan->cleared = 1;
                    $loan->save();
                }
            }

            foreach ($deductions['scheduled'] ?? [] as $info) {
                app(ScheduledDeductions::class)->recordTaken((int) $info['parent_id'], (float) $info['deduction_amount'], $run->period_id, $info['deduction_date']);
            }
        }
    }

    /** Undo applyLoanDeductions when an approved run is reversed. */
    private function rollBackLoanDeductions(PayrollRun $run): void
    {
        foreach ($this->runLoanDeductions($run) as $deductions) {
            if (!empty($deductions['helb'])) {
                $info = $deductions['helb'];
                $loan = HelbDeduction::find($info['parent_id']);
                if ($loan) {
                    HelbDeductionRepeat::where('parent_id', $loan->id)->where('period_id', $run->period_id)->delete();
                    $loan->loan_balance = (float) $loan->loan_balance + (float) $info['deduction_amount'];
                    $loan->cleared = 0;
                    $loan->save();
                }
            }

            foreach ($deductions['prorated'] ?? [] as $info) {
                ProratedDeductionRepeat::where('parent_id', $info['parent_id'])->where('period_id', $run->period_id)->delete();
                ProratedDeduction::where('id', $info['parent_id'])->update(['cleared' => 0]);
            }

            foreach ($deductions['scheduled'] ?? [] as $info) {
                app(ScheduledDeductions::class)->reverseTaken((int) $info['parent_id'], $run->period_id);
            }
        }
    }

    /**
     * The loan repayments parked on the run's summary rows, one entry per employee that has any.
     */
    private function runLoanDeductions(PayrollRun $run): Collection
    {
        return PayrollSummary::where('period_id', $run->period_id)
            ->whereNotNull('loan_deductions')
            ->pluck('loan_deductions');
    }

    /** In-app alert only: payroll notifications must never go out by email. */
    private function notifyAccountants(PayrollRun $run): void
    {
        $roleId = DB::table('cb_roles')->where('name', 'accountant')->value('id');
        if (!$roleId) {
            return;
        }

        foreach (DB::table('cb_role_user')->where('role_id', $roleId)->pluck('user_id') as $userId) {
            $this->notifyUser($run, (int) $userId, 'Payroll awaiting approval', 'A payroll run has been submitted for your review.');
        }
    }

    /**
     * Create an in-app notification for one user that links to the run's review page.
     */
    private function notifyUser(PayrollRun $run, ?int $userId, string $title, string $message, ?string $url = null): void
    {
        if (!$userId) {
            return;
        }

        NotificationModel::create([
            'type' => 'PayrollRun',
            'notifiable_type' => User::class,
            'notifiable_id' => $userId,
            'data' => json_encode(['period_id' => $run->period_id]),
            'title' => $title,
            'message' => $message,
            'action_url' => $url ?? route('payroll.review', $run->period_id),
        ]);
    }

    /**
     * Tell everyone who works on this payroll, in the app only: whoever processed, submitted and approved the run, plus
     * everyone who can process or approve payroll. The person who made the change is not told about their own action.
     */
    private function notifyPayrollTeam(PayrollRun $run, string $title, string $message, ?string $url = null): void
    {
        $people = collect([$run->processed_by, $run->submitted_by, $run->approved_by])
            ->merge($this->usersWithPermissions(['create_payroll_processing', 'approve_payroll_processing']))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) Auth::id());

        foreach ($people as $userId) {
            $this->notifyUser($run, $userId, $title, $message, $url);
        }
    }

    /**
     * The ids of users who hold any of the permissions, through a role or directly.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function usersWithPermissions(array $names): array
    {
        $permissionIds = DB::table('cb_permissions')->whereIn('name', $names)->pluck('id');
        if ($permissionIds->isEmpty()) {
            return [];
        }

        $roleIds = DB::table('cb_permission_role')->whereIn('permission_id', $permissionIds)->pluck('role_id');
        $userIds = DB::table('cb_role_user')->whereIn('role_id', $roleIds)->pluck('user_id');
        if (Schema::hasTable('cb_permission_user')) {
            $userIds = $userIds->merge(DB::table('cb_permission_user')->whereIn('permission_id', $permissionIds)->pluck('user_id'));
        }

        return $userIds->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
