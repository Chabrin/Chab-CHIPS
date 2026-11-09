<?php

namespace Tests\Feature;

use App\Models\NssfRate;
use App\Models\User;
use App\Services\NssfCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * NSSF comes from HR Settings > NSSF Rates: the row in force on the date, or the standard figures in
 * config/nssf.php when there is none. These tests cover the lookup, the migration and the screen.
 */
class NssfRatesTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        Schema::clearResolvedInstance('db.schema');
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('josya_users', function (Blueprint $table) { $table->id(); $table->string('name')->nullable(); });
        // The table as the old system left it, before the migration adds the limits and tier names
        Schema::create('sys_hr_nssf_rates', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_time')->nullable();
            $table->string('base_id')->nullable();
            $table->decimal('employee_perc', 8, 2)->nullable();
            $table->decimal('employer_perc', 8, 2)->nullable();
            $table->decimal('maximum_contribution', 12, 2)->nullable();
            $table->dateTime('from_date')->nullable();
            $table->integer('created_by_id')->nullable();
            $table->integer('current_status_id')->nullable();
            $table->string('taxable')->nullable();
        });
        $this->migration()->up();
        DB::table('sys_hr_nssf_rates')->delete(); // the migration started the screen with the standard row; each test sets its own
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function migration()
    {
        return require database_path('migrations/2026_11_06_100000_add_earnings_limits_to_nssf_rates.php');
    }

    private function actingAsHr(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturn(true);
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function rate(array $overrides = []): int
    {
        return DB::table('sys_hr_nssf_rates')->insertGetId($overrides + [
            'from_date' => '2026-02-01 00:00:00', 'employee_perc' => 6, 'employer_perc' => 6,
            'lower_earnings_limit' => 9000, 'upper_earnings_limit' => 108000, 'maximum_contribution' => 6480,
            'current_status_id' => 5,
        ]);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'from_date' => '2027-02-01', 'employee_perc' => 6, 'employer_perc' => 6,
            'lower_earnings_limit' => 10000, 'upper_earnings_limit' => 120000,
        ];
    }

    // ── the lookup ────────────────────────────────────────────────────────

    public function test_without_any_row_the_standard_figures_apply(): void
    {
        $calculator = new NssfCalculator();

        $result = $calculator->calculate(50000, '2026-11-01');

        $this->assertEquals([540, 2460], array_column($result['tiers'], 'amount'));
        $this->assertEquals(3000, $result['total']);
        $this->assertSame('standard', $calculator->settingsFor('2026-11-01')['source']);
    }

    public function test_the_row_in_force_on_the_date_is_used(): void
    {
        $this->rate(['from_date' => '2025-02-01 00:00:00', 'lower_earnings_limit' => 8000, 'upper_earnings_limit' => 72000]);
        $this->rate();
        $this->rate(['from_date' => '2027-02-01 00:00:00', 'employee_perc' => 7, 'lower_earnings_limit' => 10000, 'upper_earnings_limit' => 120000]);
        $calculator = new NssfCalculator();

        $this->assertEquals(4320, $calculator->calculate(100000, '2025-06-01')['total']); // 6% of 8,000 + 6% of 64,000
        $this->assertEquals(6000, $calculator->calculate(100000, '2026-11-30')['total']); // 6% of 9,000 + 6% of 91,000
        $this->assertEquals(7000, $calculator->calculate(100000, '2027-03-01')['total']); // 7% of 10,000 + 7% of 90,000
    }

    public function test_a_row_starts_on_its_from_date(): void
    {
        $this->rate();
        $this->rate(['from_date' => '2027-02-01 00:00:00', 'lower_earnings_limit' => 10000, 'upper_earnings_limit' => 120000]);
        $calculator = new NssfCalculator();

        $this->assertSame(9000.0, $calculator->settingsFor('2027-01-31')['lower_earnings_limit']);
        $this->assertSame(10000.0, $calculator->settingsFor('2027-02-01')['lower_earnings_limit']);
    }

    public function test_before_the_first_row_the_standard_figures_apply(): void
    {
        config(['nssf.lower_earnings_limit' => 7000]);
        $this->rate();
        $calculator = new NssfCalculator();

        $this->assertSame('standard', $calculator->settingsFor('2025-12-31')['source']);
        $this->assertSame(7000.0, $calculator->settingsFor('2025-12-31')['lower_earnings_limit']);
        $this->assertSame('settings', $calculator->settingsFor('2026-02-01')['source']);
    }

    public function test_rows_that_are_inactive_or_have_no_limits_are_ignored(): void
    {
        $usable = $this->rate(['from_date' => '2025-02-01 00:00:00', 'lower_earnings_limit' => 8000, 'upper_earnings_limit' => 72000]);
        $this->rate(['from_date' => '2026-02-01 00:00:00', 'current_status_id' => 6]);                                   // switched off
        $this->rate(['from_date' => '2026-03-01 00:00:00', 'lower_earnings_limit' => null, 'upper_earnings_limit' => null]); // from the old system
        $this->rate(['from_date' => '2026-03-15 00:00:00', 'lower_earnings_limit' => null]);                              // no Tier I limit
        $this->rate(['from_date' => '2026-03-20 00:00:00', 'upper_earnings_limit' => null]);                              // no Tier II limit
        $this->rate(['from_date' => '2026-04-01 00:00:00', 'employee_perc' => null]);                                     // no rate

        $settings = (new NssfCalculator())->settingsFor('2026-11-01');

        $this->assertSame($usable, $settings['id']);
        $this->assertSame(8000.0, $settings['lower_earnings_limit']);
    }

    public function test_the_rate_comes_from_the_row(): void
    {
        $this->rate(['employee_perc' => 5]);

        $this->assertEquals(5000, (new NssfCalculator())->calculate(100000, '2026-11-01')['total']); // 5% of 9,000 + 5% of 91,000
    }

    public function test_tier_names_come_from_the_row_and_fall_back_to_the_standard_names(): void
    {
        $this->rate(['tier1_name' => 'Tier I (Fund A)', 'tier2_name' => null]);

        $tiers = (new NssfCalculator())->calculate(50000, '2026-11-01')['tiers'];

        $this->assertSame('Tier I (Fund A)', $tiers[0]['name']);
        $this->assertSame('Tier II (Britam)', $tiers[1]['name']);

        $this->rate(['from_date' => '2027-02-01 00:00:00', 'tier1_name' => null, 'tier2_name' => 'Tier II (Fund B)']);
        $later = (new NssfCalculator())->calculate(50000, '2027-03-01')['tiers'];

        $this->assertSame('Tier I (NSSF)', $later[0]['name']);
        $this->assertSame('Tier II (Fund B)', $later[1]['name']);
    }

    public function test_the_wording_follows_the_row(): void
    {
        $this->rate(['employee_perc' => 7, 'lower_earnings_limit' => 10000, 'upper_earnings_limit' => 120000]);

        $tiers = (new NssfCalculator())->calculate(50000, '2026-11-01')['tiers'];

        $this->assertSame('7% of pay up to 10,000', $tiers[0]['basis']);
        $this->assertSame('7% of pay from 10,000 up to 120,000', $tiers[1]['basis']);
    }

    public function test_the_database_is_asked_once_per_date(): void
    {
        $this->rate();
        $calculator = new NssfCalculator();
        $lookups = 0;
        DB::listen(function ($query) use (&$lookups) {
            if (str_contains($query->sql, 'from_date')) {
                $lookups++;
            }
        });

        $calculator->calculate(50000, '2026-11-01');
        $calculator->calculate(70000, '2026-11-01');
        $calculator->calculate(70000, '2026-11-01 10:00:00'); // the same day at another time
        $this->assertSame(1, $lookups, 'a payroll run should not ask for every staff member');

        $calculator->calculate(70000, '2026-12-01');
        $this->assertSame(2, $lookups);
    }

    public function test_until_the_migration_has_run_pay_keeps_using_the_standard_figures(): void
    {
        $this->rate(['lower_earnings_limit' => 20000, 'upper_earnings_limit' => 200000]);
        $this->migration()->down();

        $settings = (new NssfCalculator())->settingsFor('2026-11-01');

        $this->assertSame('standard', $settings['source']);
        $this->assertSame(9000.0, $settings['lower_earnings_limit']);
    }

    // ── the migration ────────────────────────────────────────────────────

    public function test_the_migration_starts_the_screen_with_the_standard_figures(): void
    {
        $this->migration()->up();

        $row = DB::table('sys_hr_nssf_rates')->sole();
        $this->assertSame('2026-02-01', substr($row->from_date, 0, 10));
        $this->assertEquals([6, 6, 9000, 108000, 6480], [$row->employee_perc, $row->employer_perc, $row->lower_earnings_limit, $row->upper_earnings_limit, $row->maximum_contribution]);
        $this->assertSame(['Tier I (NSSF)', 'Tier II (Britam)'], [$row->tier1_name, $row->tier2_name]);
        $this->assertEquals(5, $row->current_status_id);
    }

    public function test_the_migration_does_not_add_the_standard_row_twice_or_over_a_usable_row(): void
    {
        $this->migration()->up();
        $this->migration()->up();
        $this->assertSame(1, DB::table('sys_hr_nssf_rates')->count());

        DB::table('sys_hr_nssf_rates')->delete();
        $this->rate(['employee_perc' => 5]);
        $this->migration()->up();
        $this->assertSame(1, DB::table('sys_hr_nssf_rates')->count());
    }

    public function test_the_migration_keeps_rows_from_the_old_system_and_adds_the_standard_row_beside_them(): void
    {
        $this->migration()->down();
        DB::table('sys_hr_nssf_rates')->insert(['from_date' => '2013-01-01 00:00:00', 'employee_perc' => 5, 'employer_perc' => 5, 'maximum_contribution' => 200, 'current_status_id' => 5]);

        $this->migration()->up();

        $this->assertSame(2, DB::table('sys_hr_nssf_rates')->count());
        $this->assertSame(5.0, (float) DB::table('sys_hr_nssf_rates')->where('maximum_contribution', 200)->value('employee_perc'));
        $this->assertSame('settings', (new NssfCalculator())->settingsFor('2026-11-01')['source']);
        $this->assertSame('standard', (new NssfCalculator())->settingsFor('2020-01-01')['source'], 'the old row has no limits, so it is not used');
    }

    // ── the screen ────────────────────────────────────────────────────

    public function test_a_rate_can_be_added_from_the_screen(): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.nssfRates.store'), $this->form(['tier1_name' => 'Tier I (Fund A)']))->assertOk()->assertJson(['success' => true]);

        $row = DB::table('sys_hr_nssf_rates')->sole();
        $this->assertSame('2027-02-01', substr($row->from_date, 0, 10));
        $this->assertEquals([6, 6, 10000, 120000], [$row->employee_perc, $row->employer_perc, $row->lower_earnings_limit, $row->upper_earnings_limit]);
        $this->assertEquals(7200, $row->maximum_contribution, 'the rate on the Tier II limit');
        $this->assertSame('Tier I (Fund A)', $row->tier1_name);
        $this->assertNull($row->tier2_name);
        $this->assertEquals([5, 7], [$row->current_status_id, $row->created_by_id]);
    }

    public function test_a_saved_rate_is_used_by_payroll_from_its_date(): void
    {
        $this->actingAsHr();
        $this->postJson(route('ajax.nssfRates.store'), $this->form(['from_date' => '2026-11-01', 'employee_perc' => 5, 'lower_earnings_limit' => 10000, 'upper_earnings_limit' => 100000]))->assertOk();

        $calculator = new NssfCalculator();

        $this->assertEquals(3000, $calculator->calculate(50000, '2026-10-31')['total']);      // standard
        $this->assertEquals(2500, $calculator->calculate(50000, '2026-11-01')['total']);      // 5% of 50,000
        $this->assertEquals(5000, $calculator->calculate(150000, '2026-11-01')['total']);     // 5% of the 100,000 limit
    }

    #[DataProvider('badForms')]
    public function test_a_rate_that_does_not_make_sense_is_refused(array $overrides, string $field): void
    {
        $this->actingAsHr();

        $this->postJson(route('ajax.nssfRates.store'), $this->form($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame(0, DB::table('sys_hr_nssf_rates')->count());
    }

    public static function badForms(): array
    {
        return [
            'no from date' => [['from_date' => ''], 'from_date'],
            'no Tier I limit' => [['lower_earnings_limit' => ''], 'lower_earnings_limit'],
            'no Tier II limit' => [['upper_earnings_limit' => ''], 'upper_earnings_limit'],
            'Tier II limit below Tier I' => [['upper_earnings_limit' => 5000], 'upper_earnings_limit'],
            'Tier II limit equal to Tier I' => [['upper_earnings_limit' => 10000], 'upper_earnings_limit'],
            'no rate' => [['employee_perc' => 0], 'employee_perc'],
            'rate over 100' => [['employee_perc' => 101], 'employee_perc'],
            'negative employer rate' => [['employer_perc' => -1], 'employer_perc'],
            'a name that is too long' => [['tier2_name' => str_repeat('x', 61)], 'tier2_name'],
        ];
    }

    public function test_the_tier_limit_message_is_in_plain_words(): void
    {
        $this->actingAsHr();

        $errors = $this->postJson(route('ajax.nssfRates.store'), $this->form(['upper_earnings_limit' => 5000]))->json('errors');

        $this->assertSame('The Tier II limit must be higher than the Tier I limit.', $errors['upper_earnings_limit'][0]);
    }

    public function test_two_rates_cannot_start_on_the_same_day(): void
    {
        $this->actingAsHr();
        $this->rate(['from_date' => '2027-02-01 00:00:00']);

        $errors = $this->postJson(route('ajax.nssfRates.store'), $this->form())->assertUnprocessable()->json('errors');

        $this->assertStringContainsString('already starts on this date', $errors['from_date'][0]);
    }

    public function test_a_switched_off_rate_does_not_block_its_date(): void
    {
        $this->actingAsHr();
        $this->rate(['from_date' => '2027-02-01 00:00:00', 'current_status_id' => 6]);

        $this->postJson(route('ajax.nssfRates.store'), $this->form())->assertOk();
    }

    public function test_a_rate_can_be_edited_and_keeps_its_own_date(): void
    {
        $this->actingAsHr();
        $id = $this->rate(['from_date' => '2027-02-01 00:00:00', 'tier1_name' => 'Old name']);

        $this->postJson(route('ajax.nssfRates.update', $id), $this->form(['employee_perc' => 6.5, 'upper_earnings_limit' => 200000, 'tier1_name' => '']))
            ->assertOk()->assertJson(['success' => true]);

        $row = DB::table('sys_hr_nssf_rates')->find($id);
        $this->assertEquals([6.5, 200000, 13000], [$row->employee_perc, $row->upper_earnings_limit, $row->maximum_contribution]);
        $this->assertNull($row->tier1_name, 'a blank name goes back to the standard one');
        $this->assertSame(1, DB::table('sys_hr_nssf_rates')->count());
    }

    public function test_a_rate_cannot_be_moved_onto_another_rates_date(): void
    {
        $this->actingAsHr();
        $this->rate(['from_date' => '2027-02-01 00:00:00']);
        $other = $this->rate(['from_date' => '2028-02-01 00:00:00']);

        $this->postJson(route('ajax.nssfRates.update', $other), $this->form())->assertUnprocessable()->assertJsonValidationErrors('from_date');
    }

    public function test_deleting_the_row_in_use_goes_back_to_the_standard_figures(): void
    {
        $this->actingAsHr();
        $id = $this->rate(['lower_earnings_limit' => 20000, 'upper_earnings_limit' => 200000]);
        $this->assertSame('settings', (new NssfCalculator())->settingsFor('2026-11-01')['source']);

        $this->deleteJson(route('ajax.nssfRates.delete', $id))->assertOk()->assertJson(['success' => true]);

        $this->assertSame('standard', (new NssfCalculator())->settingsFor('2026-11-01')['source']);
    }

    public function test_the_list_marks_the_row_in_use_today(): void
    {
        $this->actingAsHr();
        $this->rate(['from_date' => '2020-01-01 00:00:00']);
        $current = $this->rate(['from_date' => now()->subMonth()->toDateString() . ' 00:00:00']);
        $future = $this->rate(['from_date' => now()->addYear()->toDateString() . ' 00:00:00']);

        $rows = collect($this->getJson(route('ajax.nssfRates.list'))->assertOk()->json('nssfrates'))->keyBy('id');

        $this->assertTrue($rows[$current]['in_use']);
        $this->assertSame([false, false], [$rows[$future]['in_use'], $rows->except([$current, $future])->first()['in_use']]);
        $this->assertSame([9000, 108000], [(int) $rows[$current]['lower_earnings_limit'], (int) $rows[$current]['upper_earnings_limit']]);
        $this->assertTrue($rows[$current]['can_update']);
    }

    public function test_the_list_has_no_row_in_use_when_there_are_no_rows(): void
    {
        $this->actingAsHr();

        $this->getJson(route('ajax.nssfRates.list'))->assertOk()->assertJson(['nssfrates' => []]);
    }

    // ── the page ──────────────────────────────────────────────────────

    public function test_the_page_says_the_standard_figures_are_in_use_when_no_row_is_in_force(): void
    {
        $this->actingAsHr();

        $page = $this->get(route('settings.hr.nssf.rates'))->assertOk();

        $page->assertSee('id="nssfStandardNotice"', false);
        $page->assertDontSee('alert alert-info d-none', false);
        $page->assertSee('6% of pay, Tier I up to 9,000', false);
        $page->assertSee('Tier II up to 108,000', false);
    }

    public function test_the_page_hides_that_notice_when_a_row_is_in_force(): void
    {
        $this->actingAsHr();
        $this->rate();

        $this->get(route('settings.hr.nssf.rates'))->assertOk()->assertSee('alert alert-info d-none', false);
    }

    public function test_the_screen_asks_for_the_limits_and_no_longer_for_the_unused_fields(): void
    {
        $this->actingAsHr();

        $page = $this->get(route('settings.hr.nssf.rates'))->assertOk();

        foreach (['name="lower_earnings_limit"', 'name="upper_earnings_limit"', 'name="tier1_name"', 'name="tier2_name"', 'name="employee_perc"', 'name="employer_perc"', 'name="from_date"'] as $field) {
            $page->assertSee($field, false);
        }
        foreach (['name="base_id"', 'name="taxable"', 'name="maximum_contribution"', 'name="rate"'] as $gone) {
            $page->assertDontSee($gone, false);
        }
    }
}
