<?php

namespace Tests\Feature;

use App\Http\Controllers\PayrollReviewController;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The review grid can be narrowed to the employees an alert is about (?alert_filter=...), so HR can see exactly who is
 * missing bank details, has negative pay or holds several contracts before the run goes to the accountant.
 */
class PayrollReviewAlertFilterTest extends TestCase
{
    private const PERIOD = 217;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Schema::clearResolvedInstance('db.schema');
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_set_banks', function (Blueprint $t) { $t->id(); $t->string('bank_name'); });
        Schema::create('sys_set_departments', function (Blueprint $t) { $t->id(); $t->string('department_name'); });
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('full_names')->nullable();
            $t->string('account_number')->nullable();
            $t->integer('bank_name')->nullable();
            $t->integer('department_id')->nullable();
            $t->integer('current_status_id')->default(1);
        });
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('sys_hr_payroll_summary', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->string('staff_number_id')->nullable();
            $t->date('period')->nullable();
            $t->integer('period_id')->nullable();
            $t->decimal('basic_salary', 15, 2)->default(0);
            $t->decimal('total_earnings', 15, 2)->default(0);
            $t->decimal('tax_payable', 15, 2)->default(0);
            $t->decimal('nssf', 15, 2)->default(0);
            $t->decimal('nhif', 15, 2)->default(0);
            $t->decimal('housing_levy', 15, 2)->default(0);
            $t->decimal('total_deductions', 15, 2)->default(0);
            $t->decimal('net_pay', 15, 2)->default(0);
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('um_tax_bands', function (Blueprint $t) {
            $t->id();
            $t->decimal('range_from', 15, 2);
            $t->decimal('range_to', 15, 2)->nullable();
            $t->decimal('tax_rate', 5, 2);
            $t->boolean('is_last')->default(false);
        });
        DB::table('sys_set_banks')->insert(['id' => 1, 'bank_name' => 'Test Bank']);
        DB::table('sys_set_departments')->insert(['id' => 1, 'department_name' => 'Property Management']);
    }

    private function actingWith(array $permissions = ['read_payroll_processing', 'update_staff']): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = 9;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    /** A staff member with working bank details unless the overrides say otherwise. */
    private function staff(string $name, array $overrides = []): int
    {
        return DB::table('sys_staff')->insertGetId($overrides + ['staff_number' => 'E-' . $name, 'full_names' => $name, 'account_number' => '0123456', 'bank_name' => 1, 'department_id' => 1]);
    }

    private function summary(int $staffId, float $net = 50000, array $overrides = [], int $period = self::PERIOD): int
    {
        return DB::table('sys_hr_payroll_summary')->insertGetId($overrides + ['staff_id' => $staffId, 'period_id' => $period, 'net_pay' => $net, 'basic_salary' => 60000, 'total_earnings' => 60000, 'total_deductions' => 60000 - $net]);
    }

    /** Alice is fine; the rest each carry one or more alerts. */
    private function scene(): void
    {
        $this->summary($this->staff('Alice'));
        $this->summary($this->staff('Brian'), -500);                                         // negative pay
        $this->summary($this->staff('Carol', ['account_number' => null]));                   // no account number
        $this->summary($this->staff('Dan', ['bank_name' => 99]));                            // bank not on file
        $eve = $this->staff('Eve');
        $this->summary($eve);
        DB::table('sys_hr_employee_contracts')->insert([['staff_id' => $eve, 'current_status_id' => 5], ['staff_id' => $eve, 'current_status_id' => 5]]); // two active contracts
        $this->summary($this->staff('Frank', ['account_number' => '']), -10);                // negative pay and no bank
        $this->summary($this->staff('Gina', ['account_number' => '   ']));                   // a blank account number
        $this->summary(4040, 1000);                                                          // a summary whose staff record is gone
        $helen = $this->staff('Helen');
        $this->summary($helen);
        DB::table('sys_hr_employee_contracts')->insert([['staff_id' => $helen, 'current_status_id' => 5], ['staff_id' => $helen, 'current_status_id' => 6]]); // one active, one old
        $this->summary($this->staff('Ian'), 50000, [], 999);                                 // another payroll period
    }

    private function employees(array $query = [])
    {
        return $this->getJson(route('ajax.payroll.employees', self::PERIOD) . ($query ? '?' . http_build_query($query) : ''));
    }

    private function names($response): array
    {
        return array_column($response->assertOk()->json('employees'), 'name');
    }

    // ── the filter ───────────────────────────────────────────────────────

    public function test_without_a_filter_every_employee_in_the_run_is_listed_by_name(): void
    {
        $this->actingWith();
        $this->scene();

        $this->assertSame(['Alice', 'Brian', 'Carol', 'Dan', 'Eve', 'Frank', 'Gina', 'Helen', 'Unknown'], $this->names($this->employees()));
    }

    public function test_exactly_zero_net_pay_is_not_negative(): void
    {
        $this->actingWith();
        $this->summary($this->staff('Zero'), 0);
        $this->summary($this->staff('Owes'), -0.01);

        $response = $this->employees(['alert_filter' => 'negative_net']);

        $this->assertSame(['Owes'], $this->names($response));
        $this->assertSame(1, $response->json('alert_counts.negative_net'));
    }

    public function test_negative_net_keeps_only_those_with_negative_pay(): void
    {
        $this->actingWith();
        $this->scene();

        $this->assertSame(['Brian', 'Frank'], $this->names($this->employees(['alert_filter' => 'negative_net'])));
    }

    public function test_no_bank_keeps_those_with_no_account_a_blank_account_or_a_bank_that_is_not_on_file(): void
    {
        $this->actingWith();
        $this->scene();

        $this->assertSame(['Carol', 'Dan', 'Frank', 'Gina', 'Unknown'], $this->names($this->employees(['alert_filter' => 'no_bank'])));
    }

    public function test_multiple_contracts_keeps_only_staff_with_more_than_one_active_contract(): void
    {
        $this->actingWith();
        $this->scene();

        $this->assertSame(['Eve'], $this->names($this->employees(['alert_filter' => 'multiple_contracts'])));
    }

    public function test_an_empty_filter_means_everyone(): void
    {
        $this->actingWith();
        $this->scene();

        $this->assertCount(9, $this->employees(['alert_filter' => ''])->assertOk()->json('employees'));
    }

    public function test_a_filter_nobody_matches_gives_an_empty_list_not_an_error(): void
    {
        $this->actingWith();
        $this->summary($this->staff('Alice'));

        $response = $this->employees(['alert_filter' => 'negative_net'])->assertOk();

        $this->assertSame([], $response->json('employees'));
        $this->assertSame(['negative_net' => 0, 'no_bank' => 0, 'multiple_contracts' => 0], $response->json('alert_counts'));
    }

    public function test_an_unknown_filter_is_refused(): void
    {
        $this->actingWith();
        $this->scene();

        $this->employees(['alert_filter' => 'everything'])->assertStatus(422)->assertJson(['error' => 'Unknown alert filter.']);
        $this->employees(['alert_filter' => ['no_bank']])->assertStatus(422);
    }

    public function test_only_the_run_in_question_is_listed(): void
    {
        $this->actingWith();
        $this->scene();

        $this->assertNotContains('Ian', $this->names($this->employees()));
    }

    // ── counts and flags ─────────────────────────────────────────────────

    public function test_the_counts_always_cover_the_whole_run_even_when_filtered(): void
    {
        $this->actingWith();
        $this->scene();

        $expected = ['negative_net' => 2, 'no_bank' => 5, 'multiple_contracts' => 1];

        $this->assertSame($expected, $this->employees()->json('alert_counts'));
        $this->assertSame($expected, $this->employees(['alert_filter' => 'negative_net'])->json('alert_counts'));
    }

    public function test_each_row_says_which_alerts_it_carries_and_flags_them_in_words(): void
    {
        $this->actingWith();
        $this->scene();

        $rows = collect($this->employees()->json('employees'))->keyBy('name');

        $this->assertSame([[], []], [$rows['Alice']['alerts'], $rows['Alice']['flags']]);
        $this->assertSame(['negative_net', 'no_bank'], $rows['Frank']['alerts']);
        $this->assertSame(['Negative pay', 'No bank details'], $rows['Frank']['flags']);
        $this->assertSame(['Several contracts'], $rows['Eve']['flags']);
    }

    public function test_the_filter_shows_exactly_the_rows_flagged_for_it(): void
    {
        $this->actingWith();
        $this->scene();
        $all = collect($this->employees()->json('employees'));

        foreach (PayrollReviewController::ROW_ALERTS as $key => $label) {
            $flagged = $all->filter(fn ($row) => in_array($label, $row['flags'], true))->pluck('name')->all();

            $this->assertSame($flagged, $this->names($this->employees(['alert_filter' => $key])), $key);
            $this->assertNotEmpty($flagged, $key);
        }
    }

    // ── editing the staff record ──────────────────────────────────────────

    public function test_rows_link_to_the_staff_record_for_people_who_may_edit_staff(): void
    {
        $this->actingWith(['read_payroll_processing', 'update_staff']);
        $id = $this->staff('Carol', ['account_number' => null]);
        $this->summary($id);
        $this->summary(4040);

        $rows = collect($this->employees()->json('employees'))->keyBy('name');

        $this->assertSame($id, $rows['Carol']['staff_id']);
        $url = $rows['Carol']['edit_url'];
        $this->assertStringStartsWith(url('/hr/employees') . '/', $url);
        $this->assertStringEndsWith('/edit', $url);
        $this->assertSame($id, Crypt::decrypt(urldecode(basename(dirname($url)))));
        $this->assertNull($rows['Unknown']['edit_url'], 'no staff record to open');
        $this->assertNull($rows['Unknown']['staff_id']);
    }

    public function test_without_permission_to_edit_staff_there_is_no_link(): void
    {
        $this->actingWith(['read_payroll_processing']);
        $this->summary($this->staff('Carol'));

        $this->assertNull($this->employees()->assertOk()->json('employees.0.edit_url'));
    }

    public function test_reading_the_list_still_needs_payroll_access(): void
    {
        $this->actingWith(['update_staff']);
        $this->summary($this->staff('Carol'));

        $this->employees(['alert_filter' => 'no_bank'])->assertForbidden();
    }

    // ── the alerts shown at the top of the page ───────────────────────────

    private function alerts(): array
    {
        $method = new ReflectionMethod(PayrollReviewController::class, 'runAlerts');
        $method->setAccessible(true);

        return collect($method->invoke(new PayrollReviewController(), self::PERIOD))->keyBy('key')->all();
    }

    public function test_the_alerts_say_what_they_are_about_and_which_ones_can_filter_the_grid(): void
    {
        $this->actingWith();
        $this->scene();

        $alerts = $this->alerts();

        $this->assertSame('2 employee(s) have negative net pay.', $alerts['negative_net']['message']);
        $this->assertSame('5 employee(s) have no bank details and cannot be included in the EFT file.', $alerts['no_bank']['message']);
        $this->assertSame('1 staff have more than one active contract. Only the latest contract was paid.', $alerts['multiple_contracts']['message']);
        $this->assertSame(['negative_net', 'no_bank', 'multiple_contracts'], array_values(array_map(fn ($alert) => $alert['filter'], array_filter($alerts, fn ($alert) => $alert['filter']))));
        $this->assertSame([2, 5, 1], [$alerts['negative_net']['count'], $alerts['no_bank']['count'], $alerts['multiple_contracts']['count']]);
        $this->assertSame(['danger', 'danger', 'warning'], [$alerts['negative_net']['tone'], $alerts['no_bank']['tone'], $alerts['multiple_contracts']['tone']]);
    }

    public function test_a_run_with_nothing_wrong_has_no_alerts(): void
    {
        $this->actingWith();
        $id = $this->staff('Alice');
        $this->summary($id);
        DB::table('sys_hr_employee_contracts')->insert(['staff_id' => $id, 'current_status_id' => 5]);

        $this->assertSame([], $this->alerts());
    }

    public function test_active_staff_with_no_contract_are_listed_by_name_because_they_are_not_in_the_run(): void
    {
        $this->actingWith();
        $paid = $this->staff('Alice');
        $this->summary($paid);
        DB::table('sys_hr_employee_contracts')->insert(['staff_id' => $paid, 'current_status_id' => 5]);
        $this->staff('Zane');
        $this->staff('Beth');
        $this->staff('Gone', ['current_status_id' => 3]); // terminated staff are not expected to have a contract

        $alert = $this->alerts()['no_contract'];

        $this->assertNull($alert['filter']);
        $this->assertSame(2, $alert['count']);
        $this->assertSame('2 active staff have no active contract and are not in this run.', $alert['message']);
        $this->assertSame([['number' => 'E-Beth', 'name' => 'Beth'], ['number' => 'E-Zane', 'name' => 'Zane']], $alert['staff']);
        $this->assertSame(0, $alert['more']);
    }

    public function test_a_long_list_of_staff_without_a_contract_is_cut_at_a_hundred(): void
    {
        $this->actingWith();
        foreach (range(1, 105) as $n) {
            $this->staff(sprintf('Person %03d', $n));
        }

        $alert = $this->alerts()['no_contract'];

        $this->assertSame(105, $alert['count']);
        $this->assertCount(100, $alert['staff']);
        $this->assertSame(5, $alert['more']);
    }

    public function test_overlapping_tax_bands_point_to_the_tax_bands_page(): void
    {
        $this->actingWith();
        DB::table('um_tax_bands')->insert([
            ['range_from' => 0, 'range_to' => 24000, 'tax_rate' => 10, 'is_last' => false],
            ['range_from' => 20000, 'range_to' => null, 'tax_rate' => 30, 'is_last' => true],
        ]);

        $alert = $this->alerts()['tax_bands'];

        $this->assertNull($alert['filter']);
        $this->assertSame(['label' => 'Open Tax Bands', 'url' => route('payroll.tax_bands')], $alert['link']);
    }

    public function test_the_alert_filters_are_the_row_alerts_and_nothing_else(): void
    {
        $this->assertSame(['negative_net', 'no_bank', 'multiple_contracts'], array_keys(PayrollReviewController::ROW_ALERTS));
    }
}
