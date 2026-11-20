<?php

namespace Tests\Feature;

use App\Models\ContractChange;
use App\Models\PayrollPeriod;
use App\Models\PayrollPeriodItem;
use App\Models\PayrollRun;
use App\Services\PayrollChangeFeed;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsPayrollRunTables;
use Tests\TestCase;

/**
 * Before a payroll is processed (and when the accountant reviews it) the user is shown everything that changed since the
 * last payroll and could change pay: who changed it, when, and from what to what.
 */
class PayrollChangeFeedTest extends TestCase
{
    use BuildsPayrollRunTables;

    private const OCT = 216;
    private const NOV = 217;
    private const ALICE = 1;
    private const BOB = 2;
    private const CAROL = 3;        // no contract
    private const DIRECTOR = 5;
    private const HR = 6;
    private const LAST_PROCESSED = '2026-11-02 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        // The payroll queries use raw table names, so run without the sqlite test prefix like production does.
        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        Schema::clearResolvedInstance('db.schema');

        Model::unsetEventDispatcher(); // skip the audit-log observers; the log is written by hand here
        $this->buildPayrollRunTables();

        Schema::create('cb_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('table_name');
            $t->unsignedBigInteger('record_id');
            $t->string('operation');
            $t->text('old_data')->nullable();
            $t->text('new_data')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_changes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->unsignedBigInteger('staff_id');
            $t->string('field', 30);
            $t->unsignedBigInteger('element_id')->nullable();
            $t->string('old_value')->nullable();
            $t->string('new_value')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) {
            $t->id();
            $t->string('element_name');
            $t->string('element_type')->default('1');
        });
        Schema::create('payroll_period_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('period_id');
            $t->unsignedBigInteger('staff_id')->nullable();
            $t->unsignedBigInteger('element_id');
            $t->decimal('amount', 15, 2);
            $t->string('note')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('sys_hr_nssf_rates', function (Blueprint $t) {
            $t->id();
            $t->date('from_date')->nullable();
        });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) { $t->id(); $t->string('position_name')->nullable(); });
        Schema::create('sys_hr_leave_types', function (Blueprint $t) { $t->id(); $t->string('type_name'); });
        Schema::create('sys_hr_leave_requests', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id');
            $t->integer('leave_type_id');
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('sys_hr_leave_requests_234_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->date('leave_date');
            $t->decimal('day_fraction', 5, 2)->nullable();
            $t->decimal('pay_rate', 5, 2)->nullable();
            $t->integer('current_status_id')->default(5);
        });

        DB::table('josya_users')->insert([['id' => self::DIRECTOR, 'name' => 'Dan Director'], ['id' => self::HR, 'name' => 'Hana HR']]);
        DB::table('sys_set_banks')->insert([['id' => 1, 'bank_name' => 'Equity'], ['id' => 2, 'bank_name' => 'Co-op']]);
        DB::table('sys_staff')->insert([
            ['id' => self::ALICE, 'staff_number' => 'S1', 'full_names' => 'Alice Adams', 'disability' => 'no'],
            ['id' => self::BOB, 'staff_number' => 'S2', 'full_names' => 'Bob Brown', 'disability' => 'no'],
            ['id' => self::CAROL, 'staff_number' => 'S3', 'full_names' => 'Carol Clark', 'disability' => 'no'],
        ]);
        DB::table('sys_hr_employee_contracts')->insert([
            ['id' => 10, 'staff_id' => self::ALICE, 'current_status_id' => 5, 'basic_salary' => 50000],
            ['id' => 11, 'staff_id' => self::BOB, 'current_status_id' => 5, 'basic_salary' => 40000],
        ]);

        // October was processed on 2 Nov and has been paid; November is the payroll about to be run
        DB::table('sys_hr_payroll_periods')->insert([
            ['id' => self::OCT, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'current_status_id' => 5],
            ['id' => self::NOV, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'current_status_id' => 5],
        ]);
        PayrollRun::create(['period_id' => self::OCT, 'status' => PayrollRun::STATUS_PAID, 'processed_by' => self::HR, 'processed_at' => self::LAST_PROCESSED]);
    }

    private function feed(): array
    {
        return app(PayrollChangeFeed::class)->forPeriod(PayrollPeriod::findOrFail(self::NOV));
    }

    private function group(array $feed, string $key): ?array
    {
        return collect($feed['groups'])->firstWhere('key', $key);
    }

    /** The rows of one group as "who | what | detail", for readable comparisons. */
    private function lines(array $feed, string $key): array
    {
        return collect($this->group($feed, $key)['rows'] ?? [])->map(fn ($r) => "{$r['who']} | {$r['what']} | {$r['detail']}")->all();
    }

    private function audit(string $table, int $recordId, string $operation, ?array $old, ?array $new, string $at = '2026-11-10 10:02:00', ?int $by = self::DIRECTOR): void
    {
        DB::table('cb_audit_logs')->insert([
            'table_name' => $table, 'record_id' => $recordId, 'operation' => $operation,
            'old_data' => $old === null ? null : json_encode($old), 'new_data' => $new === null ? null : json_encode($new),
            'user_id' => $by, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function salaryChange(int $contractId, int $staffId, $old, $new, string $at = '2026-11-10 10:02:00', ?int $by = self::DIRECTOR): void
    {
        ContractChange::create(['contract_id' => $contractId, 'staff_id' => $staffId, 'field' => ContractChange::BASIC_SALARY, 'old_value' => $old, 'new_value' => $new, 'changed_by' => $by, 'created_at' => $at]);
    }

    // ── what the changes are measured from ───────────────────────────────

    public function test_changes_are_measured_from_the_last_payroll_whether_it_was_approved_or_paid(): void
    {
        $this->salaryChange(10, self::ALICE, 45000, 50000, '2026-11-01 08:00:00');   // before the last payroll was processed
        $this->salaryChange(10, self::ALICE, 50000, 65000, '2026-11-10 10:02:00');   // after it

        $paid = $this->feed();
        $this->assertSame(1, $paid['changes_total']);
        $this->assertStringContainsString('October 2026', $paid['since']['label']);
        $this->assertSame('02 Nov 2026, 09:00', $paid['since']['date']);

        PayrollRun::where('period_id', self::OCT)->update(['status' => PayrollRun::STATUS_APPROVED]);
        $this->assertSame(1, $this->feed()['changes_total']);
    }

    public function test_a_run_that_was_never_approved_is_not_the_last_payroll(): void
    {
        PayrollRun::where('period_id', self::OCT)->update(['status' => PayrollRun::STATUS_DRAFT]);
        Carbon::setTestNow('2026-11-17 09:00:00');
        $this->salaryChange(10, self::ALICE, 45000, 50000, '2026-11-01 08:00:00');   // inside the last 31 days

        $feed = $this->feed();

        $this->assertSame('the last 31 days', $feed['since']['label']);
        $this->assertSame(1, $feed['changes_total']);
        Carbon::setTestNow();
    }

    // ── contracts and salaries ───────────────────────────────────────────

    public function test_a_salary_change_shows_who_made_it_when_and_from_what_to_what(): void
    {
        $this->salaryChange(10, self::ALICE, 50000, 65000);

        $feed = $this->feed();

        $this->assertSame(['Alice Adams | Basic salary | 50,000.00 to 65,000.00'], $this->lines($feed, 'contracts'));
        $row = $this->group($feed, 'contracts')['rows'][0];
        $this->assertSame('Dan Director', $row['by']);
        $this->assertSame('10 Nov 2026, 10:02', $row['at']);
        $this->assertTrue($row['high']);
        $this->assertSame(1, $feed['changes_total']);
        $this->assertSame(1, $feed['high_total']);
    }

    public function test_new_contracts_and_recurring_item_changes_are_listed(): void
    {
        ContractChange::create(['contract_id' => 11, 'staff_id' => self::BOB, 'field' => ContractChange::CONTRACT_CREATED, 'new_value' => 40000, 'changed_by' => self::HR, 'created_at' => '2026-11-12 09:00:00']);
        DB::table('sys_hr_payroll_elements')->insert(['id' => 4, 'element_name' => 'Sacco Loan']);
        ContractChange::create(['contract_id' => 10, 'staff_id' => self::ALICE, 'field' => ContractChange::ELEMENT_CHANGED, 'element_id' => 4, 'old_value' => 1000, 'new_value' => 1500, 'changed_by' => self::HR, 'created_at' => '2026-11-12 09:30:00']);

        $lines = $this->lines($this->feed(), 'contracts');

        $this->assertContains('Bob Brown | New contract | Starting salary 40,000.00', $lines);
        $this->assertContains('Alice Adams | Recurring item | Sacco Loan: 1,000.00 to 1,500.00', $lines);
    }

    public function test_contract_dates_status_and_removal_come_from_the_audit_trail(): void
    {
        $this->audit('sys_hr_employee_contracts', 10, 'update', ['date_end' => '2026-12-31 00:00:00'], ['date_end' => '2026-11-20 00:00:00']);
        $this->audit('sys_hr_employee_contracts', 11, 'update', ['current_status_id' => 5], ['current_status_id' => 6]);
        $this->audit('sys_hr_employee_contracts', 99, 'delete', ['staff_id' => self::BOB, 'basic_salary' => 40000], null);

        $lines = $this->lines($this->feed(), 'contracts');

        $this->assertContains('Alice Adams | Contract end date | 31 Dec 2026 to 20 Nov 2026', $lines);
        $this->assertContains('Bob Brown | Contract status | Active to Not active', $lines);
        $this->assertContains('Bob Brown | Contract removed | Basic salary was 40,000.00', $lines);
    }

    public function test_a_salary_changed_some_other_way_still_shows_and_is_never_listed_twice(): void
    {
        $this->salaryChange(10, self::ALICE, 50000, 65000);
        $this->audit('sys_hr_employee_contracts', 10, 'update', ['basic_salary' => 50000], ['basic_salary' => 65000]);   // the same change, seen by the audit trail too
        $this->audit('sys_hr_employee_contracts', 11, 'update', ['basic_salary' => 40000], ['basic_salary' => 90000]);   // no change log entry

        $lines = $this->lines($this->feed(), 'contracts');

        $this->assertCount(2, $lines);
        $this->assertContains('Alice Adams | Basic salary | 50,000.00 to 65,000.00', $lines);
        $this->assertContains('Bob Brown | Basic salary | 40,000.00 to 90,000.00', $lines);
    }

    // ── staff records ────────────────────────────────────────────────────

    public function test_staff_changes_that_affect_pay_are_listed_and_the_account_number_is_masked(): void
    {
        $this->audit('sys_staff', self::ALICE, 'update', ['bank_name' => 1], ['bank_name' => 2]);
        $this->audit('sys_staff', self::ALICE, 'update', ['account_number' => '0011223344'], ['account_number' => '9988776655']);
        $this->audit('sys_staff', self::BOB, 'update', ['disability' => 'no'], ['disability' => 'yes']);
        $this->audit('sys_staff', self::BOB, 'update', ['current_status_id' => 1], ['current_status_id' => 2]);

        $feed = $this->feed();
        $lines = $this->lines($feed, 'staff');

        $this->assertContains('Alice Adams | Bank | Equity to Co-op', $lines);
        $this->assertContains('Alice Adams | Bank account number | ****3344 to ****6655', $lines);
        $this->assertContains('Bob Brown | Disability (PAYE relief) | No to Yes', $lines);
        $this->assertContains('Bob Brown | Staff status | Active to Suspended', $lines);
        $this->assertStringNotContainsString('0011223344', json_encode($feed));
        $this->assertStringNotContainsString('9988776655', json_encode($feed));
        $this->assertSame(4, $this->group($feed, 'staff')['high']);
    }

    public function test_staff_changes_that_do_not_affect_pay_or_belong_to_people_with_no_contract_are_left_out(): void
    {
        $this->audit('sys_staff', self::ALICE, 'update', ['photo' => null], ['photo' => 'a.png']);
        $this->audit('sys_staff', self::ALICE, 'update', ['mobile_number' => '1'], ['mobile_number' => '2']);
        $this->audit('sys_staff', self::CAROL, 'update', ['disability' => 'no'], ['disability' => 'yes']);   // Carol has no contract

        $this->assertNull($this->group($this->feed(), 'staff'));
    }

    // ── loans and deductions ─────────────────────────────────────────────

    public function test_loan_and_deduction_changes_are_listed_but_not_the_balance_updates_a_run_makes(): void
    {
        DB::table('sys_helb_deductions')->insert(['id' => 7, 'staff_id' => self::ALICE, 'loan_balance' => 4000, 'monthly_deduction' => 1000]);
        DB::table('sys_hr_employee_amortized_deductions')->insert(['id' => 8, 'staff_id' => self::BOB, 'monthly_deduction' => 500]);

        $this->audit('sys_helb_deductions', 7, 'update', ['monthly_deduction' => 1000], ['monthly_deduction' => 2000]);
        $this->audit('sys_helb_deductions', 7, 'update', ['suspended' => 0], ['suspended' => 1]);
        $this->audit('sys_helb_deductions', 7, 'update', ['loan_balance' => 5000, 'cleared' => 0], ['loan_balance' => 4000, 'cleared' => 0], '2026-11-12 09:00:00', 8);   // a run being approved
        $this->audit('sys_hr_employee_amortized_deductions', 8, 'create', null, ['staff_id' => self::BOB, 'monthly_deduction' => 500]);

        $lines = $this->lines($this->feed(), 'deductions');

        $this->assertContains('Alice Adams | HELB monthly deduction | 1,000.00 to 2,000.00', $lines);
        $this->assertContains('Alice Adams | HELB loan | Suspended, so nothing is taken', $lines);
        $this->assertContains('Bob Brown | Deduction plan added | Monthly deduction 500.00', $lines);
        $this->assertCount(3, $lines);
    }

    // ── company-wide settings ────────────────────────────────────────────

    public function test_company_wide_settings_are_listed_as_high_impact(): void
    {
        DB::table('sys_hr_payroll_elements')->insert(['id' => 9, 'element_name' => 'House Allowance', 'element_type' => '2']);
        DB::table('um_tax_bands')->insert(['id' => 3, 'range_from' => 24001, 'range_to' => 32333, 'tax_rate' => 25]);
        $this->audit('sys_hr_payroll_elements', 9, 'update', ['amount' => 2000, 'recurrence' => 'recurring'], ['amount' => 2500, 'recurrence' => 'one_time']);
        $this->audit('um_tax_bands', 3, 'update', ['tax_rate' => 25], ['tax_rate' => 26]);
        $this->audit('sys_hr_nssf_rates', 1, 'update', ['employee_perc' => 6], ['employee_perc' => 7]);

        $feed = $this->feed();
        $lines = $this->lines($feed, 'settings');

        $this->assertContains('House Allowance | Payroll item changed | Amount: 2,000.00 to 2,500.00; How often: recurring to one_time', $lines);
        $this->assertContains('Income tax (PAYE) | Tax band 24,001 to 32,333 changed | Rate %: 25 to 26', $lines);
        $this->assertContains('NSSF | Rates changed | Employee %: 6 to 7', $lines);
        $this->assertSame(3, $this->group($feed, 'settings')['high']);
    }

    // ── what else feeds this month ───────────────────────────────────────

    public function test_one_time_items_and_unpaid_sick_leave_are_listed_but_are_not_counted_as_changes(): void
    {
        DB::table('sys_hr_payroll_elements')->insert(['id' => 1, 'element_name' => 'Bonus', 'element_type' => '2']);
        PayrollPeriodItem::create(['period_id' => self::NOV, 'staff_id' => self::ALICE, 'element_id' => 1, 'amount' => 20000, 'note' => 'Q3 target', 'created_by' => self::HR]);
        PayrollPeriodItem::create(['period_id' => self::OCT, 'staff_id' => self::BOB, 'element_id' => 1, 'amount' => 999]);   // another month

        DB::table('sys_hr_leave_types')->insert(['id' => 1, 'type_name' => 'Sick Leave'], );
        DB::table('sys_hr_leave_requests')->insert(['id' => 1, 'staff_id' => self::BOB, 'leave_type_id' => 1]);
        DB::table('sys_hr_leave_requests_234_repeat')->insert([
            ['parent_id' => 1, 'leave_date' => '2026-11-04', 'day_fraction' => 1, 'pay_rate' => 50],
            ['parent_id' => 1, 'leave_date' => '2026-11-05', 'day_fraction' => 1, 'pay_rate' => 100],
            ['parent_id' => 1, 'leave_date' => '2026-12-01', 'day_fraction' => 1, 'pay_rate' => 0],   // another month
        ]);

        $feed = $this->feed();

        $this->assertSame(['Alice Adams | Bonus | 20,000.00 (Q3 target)'], $this->lines($feed, 'one_time'));
        $this->assertSame(['Bob Brown | Unpaid sick leave | 0.5 days unpaid of 2 days sick leave'], $this->lines($feed, 'sick_leave'));
        $this->assertSame(0, $feed['changes_total']);
        $this->assertSame('included', $this->group($feed, 'one_time')['kind']);
    }

    // ── when there is nothing, and when something cannot be read ─────────

    public function test_with_nothing_changed_there_are_no_change_groups(): void
    {
        $feed = $this->feed();

        $this->assertSame(0, $feed['changes_total']);
        $this->assertSame([], $feed['groups']);
        $this->assertSame([], $feed['warnings']);
    }

    public function test_something_that_cannot_be_read_is_reported_and_the_rest_still_shows(): void
    {
        $this->salaryChange(10, self::ALICE, 50000, 65000);
        $this->audit('sys_staff', self::ALICE, 'update', ['bank_name' => 1], ['bank_name' => 2]);
        Schema::drop('sys_set_banks');

        $feed = $this->feed();

        $this->assertSame(['staff records'], $feed['warnings']);
        $this->assertSame(['Alice Adams | Basic salary | 50,000.00 to 65,000.00'], $this->lines($feed, 'contracts'));
    }

    public function test_a_long_list_is_cut_but_still_says_how_many_there_are(): void
    {
        foreach (range(1, 70) as $i) {
            $this->salaryChange(10, self::ALICE, $i, $i + 1, '2026-11-10 10:00:00');
        }

        $group = $this->group($this->feed(), 'contracts');

        $this->assertSame(70, $group['count']);
        $this->assertCount(60, $group['rows']);
        $this->assertSame(10, $group['more']);
    }

    // ── the panel HR sees before pressing Process ────────────────────────

    private function actingWith(array $permissions): void
    {
        $user = \Mockery::mock(\App\Models\User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = self::HR;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function panel(int $period = self::NOV)
    {
        return $this->getJson('/ajax/payroll/periods/' . $period . '/changes');
    }

    public function test_the_processing_screen_gets_the_panel_for_the_chosen_period(): void
    {
        $this->actingWith(['read_payroll_processing']);
        $this->salaryChange(10, self::ALICE, 50000, 65000);

        $response = $this->panel()->assertOk()->assertJson(['success' => true, 'changes_total' => 1]);

        $html = $response->json('html');
        $this->assertStringContainsString('1 change since the last payroll', $html);
        $this->assertStringContainsString('Alice Adams', $html);
        $this->assertStringContainsString('50,000.00 to 65,000.00', $html);
        $this->assertStringContainsString('by Dan Director', $html);
        $this->assertStringContainsString('10 Nov 2026, 10:02', $html);
        $this->assertStringContainsString('1 could change pay a lot', $html);
        $this->assertStringContainsString('does not stop or change the payroll', $html);
    }

    public function test_the_panel_says_so_when_nothing_has_changed(): void
    {
        $this->actingWith(['read_payroll_processing']);

        $html = $this->panel()->assertOk()->json('html');

        $this->assertStringContainsString('Nothing that affects pay has changed since the last payroll', $html);
        $this->assertStringNotContainsString('<details', $html);
    }

    public function test_the_panel_needs_payroll_access(): void
    {
        $this->actingWith([]);

        $this->panel()->assertStatus(403);
    }

    public function test_a_period_without_dates_is_refused(): void
    {
        $this->actingWith(['read_payroll_processing']);
        DB::table('sys_hr_payroll_periods')->insert(['id' => 300, 'start_date' => null, 'end_date' => null, 'current_status_id' => 5]);

        $this->panel(300)->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_names_and_notes_in_the_panel_are_escaped(): void
    {
        $this->actingWith(['read_payroll_processing']);
        DB::table('sys_staff')->where('id', self::ALICE)->update(['full_names' => '<script>alert(1)</script>']);
        $this->salaryChange(10, self::ALICE, 50000, 65000);

        $html = $this->panel()->assertOk()->json('html');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_the_panel_flags_high_impact_rows_and_groups_the_rest_folded_away(): void
    {
        $this->actingWith(['read_payroll_processing']);
        $this->salaryChange(10, self::ALICE, 50000, 65000);
        DB::table('sys_hr_payroll_elements')->insert(['id' => 1, 'element_name' => 'Bonus', 'element_type' => '2']);
        PayrollPeriodItem::create(['period_id' => self::NOV, 'staff_id' => self::ALICE, 'element_id' => 1, 'amount' => 20000, 'created_by' => self::HR]);

        $html = $this->panel()->assertOk()->json('html');

        $this->assertStringContainsString('pc-row is-high', $html);
        $this->assertStringContainsString('Also feeding this payroll', $html);
        $this->assertStringContainsString('One-time items entered for this month', $html);
        // A group with high-impact rows opens by itself; what merely feeds this month stays folded away
        $this->assertMatchesRegularExpression('/<details class="pc-group"\s+open\s*>\s*<summary>\s*<span class="pc-group-title">Contracts and salaries/', $html);
        $this->assertMatchesRegularExpression('/<details class="pc-group"\s*>\s*<summary>\s*<span class="pc-group-title">One-time items/', $html);
    }

    public function test_the_list_can_be_kept_with_the_run_and_read_back_unchanged(): void
    {
        $this->salaryChange(10, self::ALICE, 50000, 65000);
        $feed = $this->feed();

        $this->assertSame($feed, json_decode(json_encode($feed), true));
        $this->assertIsArray($feed['groups'][0]['rows'][0]);
    }
}
