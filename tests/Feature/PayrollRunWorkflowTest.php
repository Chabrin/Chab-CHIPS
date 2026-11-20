<?php

namespace Tests\Feature;

use App\Models\HelbDeduction;
use App\Models\HelbDeductionRepeat;
use App\Models\PayrollRun;
use App\Models\PayrollSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollRunWorkflowTest extends TestCase
{
    private const HR_ID = 7;
    private const ACCOUNTANT_ID = 8;
    private const PERIOD_ID = 216;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_hr_payroll_summary', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->string('staff_number_id')->nullable();
            $t->date('period')->nullable();
            $t->integer('period_id')->nullable();
            foreach (['basic_salary', 'total_earnings', 'tax_payable', 'nhif', 'nssf', 'housing_levy', 'total_deductions', 'net_pay'] as $column) {
                $t->decimal($column, 15, 2)->default(0);
            }
            $t->integer('current_status_id')->default(1);
            $t->integer('processed_by')->nullable();
            $t->integer('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
        });
        (require base_path('database/migrations/2026_11_04_000001_create_payroll_runs_tables.php'))->up();
        (require base_path('database/migrations/2026_01_23_115917_create_notifications_table.php'))->up();

        Schema::create('sys_hr_payroll_periods', function (Blueprint $t) {
            $t->id();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('full_names')->nullable();
            $t->integer('bank_name')->nullable();
            $t->string('account_number')->nullable();
            $t->integer('department_id')->nullable();
            $t->integer('current_status_id')->default(1);
        });
        Schema::create('sys_set_banks', function (Blueprint $t) {
            $t->id();
            $t->string('bank_name')->nullable();
        });
        Schema::create('sys_set_departments', function (Blueprint $t) {
            $t->id();
            $t->string('department_name')->nullable();
        });
        // The employee list also checks for staff holding several active contracts
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->integer('current_status_id')->default(5);
        });
        (require database_path('migrations/2026_11_20_100000_create_scheduled_deduction_tables.php'))->up(); // payroll now asks for the scheduled deductions due
        Schema::create('sys_helb_deductions', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->decimal('loan_balance', 15, 2)->default(0);
            $t->decimal('monthly_deduction', 15, 2)->default(0);
            $t->integer('cleared')->default(0);
            $t->integer('suspended')->default(0);
        });
        Schema::create('sys_helb_deductions_250_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->date('deduction_date')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('loan_balance', 15, 2)->default(0);
            $t->integer('current_status_id')->nullable();
            $t->integer('period_id')->nullable();
        });
        Schema::create('sys_hr_employee_amortized_deductions', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->decimal('initial_deduction', 15, 2)->default(0);
            $t->decimal('principal_amount', 15, 2)->default(0);
            $t->decimal('monthly_deduction', 15, 2)->default(0);
            $t->integer('cleared')->default(0);
            $t->integer('suspended')->default(0);
        });
        Schema::create('sys_hr_employee_amortized_deductions_252_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->date('deduction_date')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('interest_amount', 15, 2)->default(0);
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->decimal('amount_balance', 15, 2)->default(0);
            $t->integer('current_status_id')->nullable();
            $t->integer('period_id')->nullable();
        });
        Schema::create('cb_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('cb_role_user', function (Blueprint $t) {
            $t->integer('role_id');
            $t->integer('user_id');
        });

        DB::table('sys_hr_payroll_periods')->insert(['id' => self::PERIOD_ID, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
        DB::table('cb_roles')->insert(['id' => 44, 'name' => 'accountant']);
        DB::table('cb_role_user')->insert(['role_id' => 44, 'user_id' => self::ACCOUNTANT_ID]);
        DB::table('sys_set_banks')->insert(['id' => 1, 'bank_name' => 'Test Bank']);
        DB::table('sys_staff')->insert([
            ['id' => 1, 'staff_number' => 'S1', 'full_names' => 'Paid Person', 'bank_name' => 1, 'account_number' => '0012345'],
            ['id' => 2, 'staff_number' => 'S2', 'full_names' => 'No Bank Person', 'bank_name' => null, 'account_number' => null],
        ]);
        foreach ([1 => 50000, 2 => 40000] as $staffId => $netPay) {
            PayrollSummary::create([
                'staff_id' => $staffId,
                'period' => '2026-10-01',
                'period_id' => self::PERIOD_ID,
                'net_pay' => $netPay,
                'current_status_id' => 1,
            ]);
        }
    }

    private function actingWith(int $userId, array $permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = $userId;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function actingAsHr(): void
    {
        $this->actingWith(self::HR_ID, ['read_payroll_processing', 'create_payroll_processing']);
    }

    private function actingAsAccountant(): void
    {
        $this->actingWith(self::ACCOUNTANT_ID, ['read_payroll_processing', 'approve_payroll_processing']);
    }

    private function run_(string $status, int $processedBy = self::HR_ID): PayrollRun
    {
        return PayrollRun::updateOrCreate(['period_id' => self::PERIOD_ID], [
            'status' => $status,
            'processed_by' => $processedBy,
            'submitted_by' => $processedBy,
        ]);
    }

    private function post_(string $action, array $data = [])
    {
        return $this->postJson('/ajax/payroll/periods/' . self::PERIOD_ID . '/' . $action, $data);
    }

    public function test_processing_is_blocked_while_another_period_has_an_unapproved_run(): void
    {
        DB::table('sys_hr_payroll_periods')->insert(['id' => 217, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $this->actingAsHr();

        $response = $this->postJson('/ajax/payroll/process', ['period_id' => 217]);

        $response->assertStatus(422);
        $this->assertStringContainsString('October 2026 is still With Accountant', $response->json('error'));
        $this->assertSame(0, PayrollSummary::where('period_id', 217)->count());
    }

    public function test_payroll_runs_migration_is_safe_to_run_again(): void
    {
        $migration = require base_path('database/migrations/2026_11_04_000001_create_payroll_runs_tables.php');

        $migration->up();
        $this->assertSame(1, PayrollRun::count());
        $this->assertSame(PayrollRun::STATUS_DRAFT, PayrollRun::first()->status);

        $migration->up();
        $this->assertSame(1, PayrollRun::count());
    }

    public function test_backfilled_runs_keep_who_processed_and_approved_them(): void
    {
        PayrollSummary::where('period_id', self::PERIOD_ID)->update([
            'current_status_id' => 5,
            'processed_by' => self::HR_ID,
            'approved_by' => self::ACCOUNTANT_ID,
            'approved_at' => '2026-10-31 10:00:00',
        ]);

        (require base_path('database/migrations/2026_11_04_000001_create_payroll_runs_tables.php'))->up();

        $run = PayrollRun::first();
        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->status);
        $this->assertSame(self::HR_ID, $run->processed_by);
        $this->assertSame(self::ACCOUNTANT_ID, $run->approved_by);
        $this->assertNotNull($run->approved_at);
    }

    public function test_a_run_that_was_pending_before_the_migration_gets_its_loan_repayments_back_and_applies_them_once(): void
    {
        // Pending run processed under the old flow: both loans were already reduced when it was processed.
        $helb = HelbDeduction::create(['staff_id' => 1, 'loan_balance' => 2000, 'monthly_deduction' => 1000, 'cleared' => 0, 'suspended' => 0]);
        HelbDeductionRepeat::create(['parent_id' => $helb->id, 'amount' => 1000, 'loan_balance' => 2000, 'deduction_date' => '2026-10-01', 'period_id' => self::PERIOD_ID]);
        $prorated = DB::table('sys_hr_employee_amortized_deductions')->insertGetId(['staff_id' => 2, 'total_amount' => 2000, 'initial_deduction' => 0, 'principal_amount' => 2000, 'monthly_deduction' => 1000, 'cleared' => 1]);
        DB::table('sys_hr_employee_amortized_deductions_252_repeat')->insert([
            ['parent_id' => $prorated, 'deduction_date' => '2026-09-01', 'amount' => 1000, 'total_amount' => 1000, 'period_id' => 215],
            ['parent_id' => $prorated, 'deduction_date' => '2026-10-01', 'amount' => 1000, 'total_amount' => 1000, 'period_id' => self::PERIOD_ID],
        ]);

        (require base_path('database/migrations/2026_11_04_000001_create_payroll_runs_tables.php'))->up();

        // The repayments are handed back and parked on the staff members' summary rows.
        $this->assertEquals(3000, $helb->fresh()->loan_balance);
        $this->assertSame(0, HelbDeductionRepeat::where('period_id', self::PERIOD_ID)->count());
        $this->assertSame(0, (int) DB::table('sys_hr_employee_amortized_deductions')->where('id', $prorated)->value('cleared'));
        $this->assertSame(1, DB::table('sys_hr_employee_amortized_deductions_252_repeat')->count());
        $this->assertEquals(1000, PayrollSummary::where('staff_id', 1)->first()->loan_deductions['helb']['deduction_amount']);
        $this->assertEquals(1000, PayrollSummary::where('staff_id', 2)->first()->loan_deductions['prorated'][0]['deduction_amount']);

        // Approving now applies them exactly once.
        $this->actingAsHr();
        $this->post_('submit')->assertOk();
        $this->actingAsAccountant();
        $this->post_('approve')->assertOk();

        $this->assertEquals(2000, $helb->fresh()->loan_balance);
        $this->assertSame(1, HelbDeductionRepeat::where('period_id', self::PERIOD_ID)->count());
        $this->assertSame(1, (int) DB::table('sys_hr_employee_amortized_deductions')->where('id', $prorated)->value('cleared'));
        $this->assertSame(2, DB::table('sys_hr_employee_amortized_deductions_252_repeat')->count());
    }

    public function test_hr_submits_a_draft_and_accountants_get_an_in_app_alert_only(): void
    {
        $this->run_(PayrollRun::STATUS_DRAFT);
        $this->actingAsHr();

        $this->post_('submit')->assertOk();

        $this->assertSame(PayrollRun::STATUS_WITH_ACCOUNTANT, PayrollRun::first()->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => self::ACCOUNTANT_ID, 'type' => 'PayrollRun']);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_hr_cannot_approve(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $this->actingAsHr();
        $this->withoutExceptionHandling();

        try {
            $this->post_('approve');
            $this->fail('HR was able to approve a payroll run.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(PayrollRun::STATUS_WITH_ACCOUNTANT, PayrollRun::first()->status);
    }

    public function test_accountant_approval_opens_payslips_and_updates_loans_once(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $loan = HelbDeduction::create(['staff_id' => 1, 'loan_balance' => 3000, 'monthly_deduction' => 1000, 'cleared' => 0, 'suspended' => 0]);
        PayrollSummary::where('staff_id', 1)->update(['loan_deductions' => json_encode([
            'helb' => ['parent_id' => $loan->id, 'deduction_amount' => 1000, 'deduction_date' => '2026-10-01'],
            'prorated' => [],
        ])]);
        $this->assertEquals(3000, $loan->fresh()->loan_balance);

        $this->actingAsAccountant();
        $this->post_('approve')->assertOk();

        $this->assertSame(PayrollRun::STATUS_APPROVED, PayrollRun::first()->status);
        $this->assertSame(self::ACCOUNTANT_ID, PayrollRun::first()->approved_by);
        $this->assertSame(0, PayrollSummary::where('current_status_id', 1)->count());
        $this->assertEquals(2000, $loan->fresh()->loan_balance);
        $this->assertSame(1, HelbDeductionRepeat::count());

        $this->post_('approve')->assertStatus(422);
        $this->assertEquals(2000, $loan->fresh()->loan_balance);
    }

    public function test_nobody_can_approve_a_run_they_processed(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT, self::ACCOUNTANT_ID);
        $this->actingAsAccountant();

        $this->post_('approve')->assertStatus(422);

        $this->assertSame(PayrollRun::STATUS_WITH_ACCOUNTANT, PayrollRun::first()->status);
        $this->assertSame(2, PayrollSummary::where('current_status_id', 1)->count());
    }

    public function test_returning_needs_a_comment_and_lets_hr_resubmit(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $this->actingAsAccountant();

        $this->post_('return')->assertStatus(422);
        $this->post_('return', ['comment' => 'Check overtime'])->assertOk();
        $this->assertSame(PayrollRun::STATUS_RETURNED, PayrollRun::first()->status);
        $this->assertSame('Check overtime', PayrollRun::first()->return_comment);

        $this->actingAsHr();
        $this->post_('submit')->assertOk();
        $this->assertNull(PayrollRun::first()->return_comment);
    }

    public function test_hr_can_discard_a_draft_but_not_reverse_an_approved_run(): void
    {
        $this->run_(PayrollRun::STATUS_DRAFT);
        $this->actingAsHr();
        $this->post_('reverse', ['comment' => 'Wrong salaries'])->assertOk();
        $this->assertSame(0, PayrollSummary::count());
        $this->assertSame(PayrollRun::STATUS_REVERSED, PayrollRun::first()->status);

        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->withoutExceptionHandling();
        try {
            $this->post_('reverse', ['comment' => 'Trying anyway']);
            $this->fail('HR was able to reverse an approved run.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_reversing_an_approved_run_restores_loan_balances(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $loan = HelbDeduction::create(['staff_id' => 1, 'loan_balance' => 1000, 'monthly_deduction' => 1000, 'cleared' => 0, 'suspended' => 0]);
        PayrollSummary::where('staff_id', 1)->update(['loan_deductions' => json_encode([
            'helb' => ['parent_id' => $loan->id, 'deduction_amount' => 1000, 'deduction_date' => '2026-10-01'],
            'prorated' => [],
        ])]);

        $this->actingAsAccountant();
        $this->post_('approve')->assertOk();
        $this->assertSame(1, $loan->fresh()->cleared);

        $this->post_('reverse', ['comment' => 'Test run'])->assertOk();

        $this->assertEquals(1000, $loan->fresh()->loan_balance);
        $this->assertSame(0, $loan->fresh()->cleared);
        $this->assertSame(0, HelbDeductionRepeat::count());
        $this->assertSame(0, PayrollSummary::count());
    }

    public function test_a_run_with_the_accountant_cannot_be_reversed(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $this->actingAsHr();

        $this->post_('reverse', ['comment' => 'Too late'])->assertStatus(422);
        $this->assertSame(2, PayrollSummary::count());
    }

    public function test_employee_list_shows_each_person_and_flags_missing_bank_details(): void
    {
        $this->run_(PayrollRun::STATUS_DRAFT);
        $this->actingAsHr();

        $employees = $this->getJson('/ajax/payroll/periods/' . self::PERIOD_ID . '/employees')->assertOk()->json('employees');

        $this->assertCount(2, $employees);
        $this->assertSame('No Bank Person', $employees[0]['name']);
        $this->assertSame(['No bank details'], $employees[0]['flags']);
        $this->assertSame([], $employees[1]['flags']);
    }

    public function test_the_employee_list_can_be_narrowed_to_one_alert(): void
    {
        $this->run_(PayrollRun::STATUS_DRAFT);
        $this->actingAsHr();
        $url = '/ajax/payroll/periods/' . self::PERIOD_ID . '/employees';

        $response = $this->getJson($url . '?alert_filter=no_bank')->assertOk();

        $this->assertSame(['No Bank Person'], array_column($response->json('employees'), 'name'));
        $this->assertSame(['no_bank'], $response->json('employees.0.alerts'));
        $this->assertSame(1, $response->json('alert_counts.no_bank'));
        $this->assertSame([], $this->getJson($url . '?alert_filter=negative_net')->assertOk()->json('employees'));
        $this->getJson($url . '?alert_filter=whatever')->assertStatus(422);
    }

    public function test_eft_file_is_marked_test_and_leaves_out_staff_without_bank_details(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $response = $this->get('/payroll/processing/' . self::PERIOD_ID . '/eft-test')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('TEST - NOT FOR BANK', $csv);
        $this->assertStringContainsString('TEST-NOT-FOR-BANK', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('"Paid Person","Test Bank",,0012345,50000.00', $csv);
        $this->assertStringContainsString('TOTAL,"1 payments",,,50000.00', $csv);
        $this->assertStringContainsString('"No Bank Person","Missing bank details"', $csv);
    }

    public function test_eft_file_needs_an_approved_run(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $this->actingAsAccountant();

        $this->get('/payroll/processing/' . self::PERIOD_ID . '/eft-test')->assertStatus(422);
    }


    // ── scheduled deductions (UAT A6) ───────────────────────────────────────────────────────

    private function scheduledDeductionOnTheRun(float $balance, float $instalment, string $status = 'active'): \App\Models\ScheduledDeduction
    {
        $deduction = \App\Models\ScheduledDeduction::create([
            'staff_id' => 1, 'type_id' => 2, 'total_amount' => 21500, 'monthly_amount' => 2000, 'months' => 11,
            'start_month' => '2026-10-01', 'end_month' => '2027-08-01', 'balance' => $balance, 'status' => $status,
        ]);
        PayrollSummary::where('staff_id', 1)->update(['loan_deductions' => json_encode([
            'helb' => null, 'prorated' => [],
            'scheduled' => [['parent_id' => $deduction->id, 'deduction_amount' => $instalment, 'deduction_date' => '2026-10-01']],
        ])]);

        return $deduction;
    }

    public function test_approval_takes_the_scheduled_deduction_off_what_is_owed_once(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $deduction = $this->scheduledDeductionOnTheRun(21500, 2000);

        $this->actingAsAccountant();
        $this->post_('approve')->assertOk();

        $this->assertEquals(19500, $deduction->fresh()->balance);
        $this->assertSame('active', $deduction->fresh()->status);
        $repeat = $deduction->repeats()->firstOrFail();
        $this->assertEquals(2000, $repeat->amount);
        $this->assertEquals(19500, $repeat->balance);
        $this->assertSame(self::PERIOD_ID, (int) $repeat->period_id);

        $this->post_('approve')->assertStatus(422);
        $this->assertEquals(19500, $deduction->fresh()->balance);
        $this->assertSame(1, $deduction->repeats()->count());
    }

    public function test_the_last_instalment_marks_the_deduction_paid_off(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $deduction = $this->scheduledDeductionOnTheRun(1500, 1500);

        $this->actingAsAccountant();
        $this->post_('approve')->assertOk();

        $this->assertEquals(0, $deduction->fresh()->balance);
        $this->assertSame('completed', $deduction->fresh()->status);
        $this->assertContains('completed', $deduction->events()->pluck('event')->all());
    }

    public function test_reversing_the_run_puts_the_instalment_back_and_reopens_a_paid_off_deduction(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $deduction = $this->scheduledDeductionOnTheRun(1500, 1500);

        $this->actingAsAccountant();
        $this->post_('approve')->assertOk();
        $this->assertSame('completed', $deduction->fresh()->status);

        $this->post_('reverse', ['comment' => 'Test run'])->assertOk();

        $this->assertEquals(1500, $deduction->fresh()->balance);
        $this->assertSame('active', $deduction->fresh()->status);
        $this->assertSame(0, $deduction->repeats()->count());
        $this->assertContains('reversed', $deduction->events()->pluck('event')->all());
    }

    public function test_a_deduction_paused_after_the_run_was_made_still_has_the_instalment_that_was_taken_posted(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $deduction = $this->scheduledDeductionOnTheRun(21500, 2000, 'paused');

        $this->actingAsAccountant();
        $this->post_('approve')->assertOk();

        $this->assertEquals(19500, $deduction->fresh()->balance, 'the money was taken from the pay, so the balance goes down');
        $this->assertSame('paused', $deduction->fresh()->status);
    }
}
