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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class PayrollReviewController extends Controller
{
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
            'warnings' => $this->runWarnings($period->id),
            'userNames' => User::whereIn('id', array_filter([$run->processed_by, $run->submitted_by, $run->approved_by]))->pluck('name', 'id'),
            'canSubmit' => $canProcess && $isEditable,
            'canDiscard' => $canProcess && $isEditable,
            'canDecide' => $canApprove && $run->status === PayrollRun::STATUS_WITH_ACCOUNTANT && !$this->isOwnRun($run),
            'isOwnRun' => $canApprove && $this->isOwnRun($run),
            'canReverseApproved' => $canApprove && $run->status === PayrollRun::STATUS_APPROVED,
            'canDownloadEft' => $canApprove && $run->status === PayrollRun::STATUS_APPROVED,
        ]);
    }

    /** Per-employee payroll list for the review table. */
    public function employees($periodId)
    {
        $this->requirePayrollPermission('read_payroll_processing');

        return response()->json(['employees' => $this->employeeRows((int) $periodId)]);
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
            $wasApproved = $run->status === PayrollRun::STATUS_APPROVED;
            $this->requirePayrollPermission($wasApproved ? 'approve_payroll_processing' : 'create_payroll_processing');
            $this->requireStatus($run, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_RETURNED, PayrollRun::STATUS_APPROVED], 'A run with the accountant must be returned before it can be reversed.');

            if ($wasApproved) {
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

            return 'Payroll reversed. The period can now be processed again.';
        });
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
        abort_unless($run->status === PayrollRun::STATUS_APPROVED, 422, 'Only an approved payroll run can produce an EFT file.');

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

    private function requireStatus(PayrollRun $run, array $statuses, string $message): void
    {
        if (!in_array($run->status, $statuses, true)) {
            throw new \DomainException($message);
        }
    }

    private function isOwnRun(PayrollRun $run): bool
    {
        return in_array(Auth::id(), [$run->processed_by, $run->submitted_by], false);
    }

    private function rejectOwnRun(PayrollRun $run): void
    {
        if ($this->isOwnRun($run)) {
            throw new \DomainException('You processed or submitted this payroll, so another accountant must review it.');
        }
    }

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

    /** Issues the reviewer should see before the run is approved. */
    private function runWarnings(int $periodId): array
    {
        $warnings = [];
        $summaries = PayrollSummary::with('staff.bank')->where('period_id', $periodId)->get();

        $negative = $summaries->filter(fn ($summary) => (float) $summary->net_pay < 0)->count();
        if ($negative) {
            $warnings[] = "{$negative} employee(s) have negative net pay.";
        }

        $noBank = $summaries->filter(fn ($summary) => !$summary->staff || blank($summary->staff->account_number) || !$summary->staff->bank)->count();
        if ($noBank) {
            $warnings[] = "{$noBank} employee(s) have no bank details and cannot be included in the EFT file.";
        }

        $contractStaffIds = EmployeeContract::active()->whereNotNull('staff_id')->pluck('staff_id');
        $noContract = Staff::where('current_status_id', 1)->whereNotIn('id', $contractStaffIds)->count();
        if ($noContract) {
            $warnings[] = "{$noContract} active staff have no active contract and are not in this run.";
        }

        $multipleContracts = $contractStaffIds->countBy()->filter(fn ($count) => $count > 1)->count();
        if ($multipleContracts) {
            $warnings[] = "{$multipleContracts} staff have more than one active contract. Only the latest contract was paid.";
        }

        if ($this->taxBandsOverlap()) {
            $warnings[] = 'Tax bands overlap or continue after the last band, so PAYE may be wrong. Check Tax Bands.';
        }

        return $warnings;
    }

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

    private function employeeRows(int $periodId): Collection
    {
        $summaries = PayrollSummary::with(['staff.bank', 'staff.department'])
            ->where('period_id', $periodId)
            ->get();
        $previousNet = $this->previousNetPay($summaries->first()?->period);

        return $summaries->map(function (PayrollSummary $summary) use ($previousNet) {
            $staff = $summary->staff;
            $statutory = (float) $summary->tax_payable + (float) $summary->nssf + (float) $summary->nhif + (float) $summary->housing_levy;
            $previous = $previousNet->get($summary->staff_id);

            $flags = [];
            if ((float) $summary->net_pay < 0) {
                $flags[] = 'Negative pay';
            }
            if (!$staff || blank($staff->account_number) || !$staff->bank) {
                $flags[] = 'No bank details';
            }

            return [
                'id' => $summary->id,
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
                'flags' => $flags,
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
        }
    }

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

    private function notifyUser(PayrollRun $run, ?int $userId, string $title, string $message): void
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
            'action_url' => route('payroll.review', $run->period_id),
        ]);
    }
}
