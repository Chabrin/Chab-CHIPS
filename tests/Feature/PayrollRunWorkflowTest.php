<?php

namespace Tests\Feature;

use App\Http\Controllers\PayrollController;
use App\Models\EmployeeContract;
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

    public function test_overlapping_contracts_keep_only_the_latest_but_sequential_ones_stay(): void
    {
        $contract = fn (int $id, int $staffId, string $start, ?string $end) => (new EmployeeContract)->forceFill([
            'id' => $id,
            'staff_id' => $staffId,
            'date_start' => $start,
            'date_end' => $end,
        ]);
        $contracts = collect([
            $contract(1, 91, '2018-01-01', null),
            $contract(2, 91, '2018-09-01', null),
            $contract(3, 92, '2026-10-01', '2026-10-10'),
            $contract(4, 92, '2026-10-11', null),
        ]);

        $method = new \ReflectionMethod(PayrollController::class, 'withoutOverlappingContracts');
        $kept = $method->invoke(app(PayrollController::class), $contracts, '2026-10-01', '2026-10-31');

        $this->assertEqualsCanonicalizing([2, 3, 4], $kept->pluck('id')->all());
    }
}
