<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\StaffPermitExpiry;
use App\Services\StaffResidency;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Citizenship and residency: the country first, then Resident or Non-resident. Kenya is Resident by default and needs the National ID;
 * any other country needs a residency, a passport and a work permit (or Alien ID) with an expiry date, and HR is reminded before it runs out.
 */
class StaffResidencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Model::unsetEventDispatcher();
        $this->clock('2026-11-18 09:00:00');
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            foreach (['full_names', 'staff_number', 'national_id', 'passport', 'work_permit_number', 'citizenship', 'nationality', 'residency_status'] as $column) {
                $t->string($column)->nullable();
            }
            $t->integer('current_status_id')->default(1);
            $t->date('work_permit_expiry')->nullable();
        });
        Schema::create('josya_users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('block')->default(0); });
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('cb_role_user', function (Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->string('user_type'); });
        (require database_path('migrations/2026_01_23_115917_create_notifications_table.php'))->up();
        DB::table('josya_users')->insert([['id' => 1, 'name' => 'Hannah HR'], ['id' => 2, 'name' => 'Daniel Director'], ['id' => 3, 'name' => 'Peter Other']]);
        DB::table('cb_roles')->insert([['id' => 1, 'name' => 'hr_manager'], ['id' => 2, 'name' => 'directors'], ['id' => 3, 'name' => 'accountant']]);
        DB::table('cb_role_user')->insert([
            ['user_id' => 1, 'role_id' => 1, 'user_type' => User::class],
            ['user_id' => 2, 'role_id' => 2, 'user_type' => User::class],
            ['user_id' => 3, 'role_id' => 3, 'user_type' => User::class],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function clock(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date, 'Africa/Nairobi'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($date, 'Africa/Nairobi'));
    }

    private function check(array $input, bool $newStaff = true)
    {
        $rules = app(StaffResidency::class);
        $input += $rules->defaults($input);

        return Validator::make($input, $rules->rules($input['nationality'], null, $newStaff));
    }

    // ---- the country list ---------------------------------------------------------------------------------------------------

    public function test_kenya_comes_first_and_the_rest_follow_in_order(): void
    {
        $countries = StaffResidency::countries();

        $this->assertSame('Kenya', $countries[0]);
        $this->assertSame(1, array_count_values($countries)['Kenya'], 'Kenya is not listed twice');
        $rest = array_slice($countries, 1);
        $sorted = $rest;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
        $this->assertSame($sorted, $rest, 'alphabetical after Kenya');
        $this->assertContains('Uganda', $countries);
        $this->assertGreaterThan(150, count($countries));
    }

    public function test_a_country_that_is_not_on_the_list_is_refused(): void
    {
        $this->assertTrue($this->check(['nationality' => 'Atlantis', 'residency_status' => 'RESIDENT', 'national_id' => '1'])->errors()->has('nationality'));
    }

    // ---- Kenya ------------------------------------------------------------------------------------------------------------

    public function test_kenya_needs_the_national_id_and_nothing_about_a_permit(): void
    {
        $check = $this->check(['nationality' => 'Kenya', 'residency_status' => 'RESIDENT']);

        $this->assertSame(['national_id'], $check->errors()->keys());
        $this->assertTrue($this->check(['nationality' => 'Kenya', 'residency_status' => 'RESIDENT', 'national_id' => '12345678'])->passes());
    }

    public function test_a_kenyan_can_still_be_non_resident_when_hr_says_so(): void
    {
        $this->assertTrue($this->check(['nationality' => 'Kenya', 'residency_status' => 'NON_RESIDENT', 'national_id' => '12345678'])->passes());
    }

    public function test_the_country_name_is_read_without_regard_to_capitals_or_spaces(): void
    {
        $this->assertTrue(StaffResidency::isHome(' kenya '));
        $this->assertTrue(StaffResidency::isHome('KENYA'));
        $this->assertFalse(StaffResidency::isHome('Uganda'));
        $this->assertFalse(StaffResidency::isHome(null));
    }

    // ---- other countries --------------------------------------------------------------------------------------------------

    public function test_another_country_needs_residency_passport_permit_and_expiry_but_not_the_national_id(): void
    {
        $check = $this->check(['nationality' => 'Uganda', 'residency_status' => '']);

        $this->assertEqualsCanonicalizing(['residency_status', 'passport', 'work_permit_number', 'work_permit_expiry'], $check->errors()->keys());
        $this->assertFalse($check->errors()->has('national_id'));
    }

    public function test_another_country_with_everything_given_is_accepted_with_no_national_id(): void
    {
        $this->assertTrue($this->check([
            'nationality' => 'Uganda', 'residency_status' => 'NON_RESIDENT', 'passport' => 'B1234567',
            'work_permit_number' => 'WP-2026-001', 'work_permit_expiry' => '2027-06-30',
        ])->passes());
    }

    public function test_a_new_staff_members_permit_cannot_already_have_expired_but_an_existing_record_may(): void
    {
        $data = ['nationality' => 'Uganda', 'residency_status' => 'RESIDENT', 'passport' => 'B1', 'work_permit_number' => 'WP1', 'work_permit_expiry' => '2026-10-01'];

        $this->assertTrue($this->check($data, true)->errors()->has('work_permit_expiry'), 'someone being added');
        $this->assertTrue($this->check($data, false)->passes(), 'a record on file may carry a permit that has run out');
        $this->assertTrue($this->check(['work_permit_expiry' => '2026-11-18'] + $data, true)->passes(), 'today is still valid');
    }

    // ---- older forms and the old column ----------------------------------------------------------------------------------

    public function test_a_form_that_sends_only_the_old_answer_means_kenya(): void
    {
        $defaults = app(StaffResidency::class);

        $this->assertSame(['nationality' => 'Kenya', 'residency_status' => 'RESIDENT'], $defaults->defaults(['citizenship' => 'RESIDENT']));
        $this->assertSame(['nationality' => 'Kenya', 'residency_status' => 'NON_RESIDENT'], $defaults->defaults(['citizenship' => 'NO']));
        $this->assertSame(['nationality' => 'Kenya', 'residency_status' => 'RESIDENT'], $defaults->defaults([]));
        $this->assertSame(['nationality' => 'Uganda', 'residency_status' => 'NON_RESIDENT'], $defaults->defaults(['nationality' => 'Uganda', 'residency_status' => 'NON_RESIDENT', 'citizenship' => 'RESIDENT']), 'what the form sent wins');
    }

    public function test_an_answer_sent_blank_is_not_filled_in_so_hr_is_asked_for_it(): void
    {
        $defaults = app(StaffResidency::class)->defaults(['nationality' => '', 'residency_status' => '', 'citizenship' => 'RESIDENT']);

        $this->assertSame(['nationality' => '', 'residency_status' => ''], $defaults);
        $check = Validator::make($defaults + ['national_id' => '1'], app(StaffResidency::class)->rules(''));
        $this->assertEqualsCanonicalizing(['nationality', 'residency_status', 'passport', 'work_permit_number', 'work_permit_expiry'], $check->errors()->keys());
    }

    public function test_the_old_column_keeps_its_resident_or_no_answer(): void
    {
        $this->assertSame('RESIDENT', StaffResidency::legacyCitizenship('RESIDENT'));
        $this->assertSame('NO', StaffResidency::legacyCitizenship('NON_RESIDENT'));
        $this->assertSame('RESIDENT', StaffResidency::legacyCitizenship(null));
    }

    // ---- the work permit reminder -----------------------------------------------------------------------------------------

    private function permit(string $name, ?string $expiry, int $status = 1): int
    {
        return DB::table('sys_staff')->insertGetId(['full_names' => $name, 'staff_number' => 'P' . $name[0], 'work_permit_number' => 'WP', 'work_permit_expiry' => $expiry, 'current_status_id' => $status, 'nationality' => 'Uganda']);
    }

    public function test_the_summary_counts_expired_and_soon_to_expire_permits_and_skips_those_who_left(): void
    {
        $this->permit('Expired', '2026-11-01');
        $this->permit('Tomorrow', '2026-11-19');
        $this->permit('In20', '2026-12-08');
        $this->permit('In50', '2027-01-07');
        $this->permit('Far', '2027-06-01');
        $this->permit('Left', '2026-11-25', 3);
        $this->permit('None', null);

        $summary = app(StaffPermitExpiry::class)->summary();

        $this->assertSame(1, $summary['expired']);
        $this->assertSame(1, $summary['due_14']);
        $this->assertSame(2, $summary['due_30']);
        $this->assertSame(3, $summary['due_60']);
        $this->assertSame(['Expired', 'Tomorrow', 'In20', 'In50'], array_column($summary['staff'], 'name'));
        $this->assertSame(-17, $summary['staff'][0]['days']);
    }

    public function test_the_message_says_what_needs_attention_or_nothing(): void
    {
        $permits = app(StaffPermitExpiry::class);
        $this->assertNull($permits->message($permits->summary()));

        $this->permit('Expired', '2026-11-01');
        $this->permit('Tomorrow', '2026-11-19');
        $message = $permits->message($permits->summary());

        $this->assertStringContainsString('1 work permit has already expired', $message);
        $this->assertStringContainsString('1 expires within 60 days (1 within 14 days)', $message);
    }

    public function test_the_command_tells_the_hr_manager_and_directors_and_nobody_else(): void
    {
        $this->permit('Tomorrow', '2026-11-19');

        $this->artisan('hr:check-expiring-permits')->assertSuccessful();

        $recipients = Notification::where('type', 'WorkPermitExpirySummary')->pluck('notifiable_id')->sort()->values()->all();
        $this->assertSame([1, 2], $recipients, 'HR Manager and Directors, not the accountant');
        $this->assertStringContainsString('expires within 60 days', Notification::first()->message);
    }

    public function test_running_it_again_updates_the_unread_one_instead_of_adding_another(): void
    {
        $this->permit('Tomorrow', '2026-11-19');
        $this->artisan('hr:check-expiring-permits')->assertSuccessful();
        $this->permit('Next week', '2026-11-25');

        $this->artisan('hr:check-expiring-permits')->assertSuccessful();

        $this->assertSame(2, Notification::count(), 'one each, updated');
        $this->assertSame(2, Notification::first()->data['due_14']);
    }

    public function test_once_read_it_is_not_repeated_the_same_day_but_is_the_next_day(): void
    {
        $this->permit('Tomorrow', '2026-11-19');
        $this->artisan('hr:check-expiring-permits')->assertSuccessful();
        Notification::query()->update(['read_at' => now()]);

        $this->artisan('hr:check-expiring-permits')->assertSuccessful();
        $this->assertSame(2, Notification::count(), 'nothing new the same day');

        $this->clock('2026-11-19 09:00:00');
        $this->artisan('hr:check-expiring-permits')->assertSuccessful();
        $this->assertSame(4, Notification::count(), 'a fresh reminder the next day');
    }

    public function test_it_is_cleared_when_nothing_needs_attention_any_more(): void
    {
        $id = $this->permit('Tomorrow', '2026-11-19');
        $this->artisan('hr:check-expiring-permits')->assertSuccessful();

        DB::table('sys_staff')->where('id', $id)->update(['work_permit_expiry' => '2028-01-01']);
        $this->artisan('hr:check-expiring-permits')->assertSuccessful();

        $this->assertSame(0, Notification::whereNull('read_at')->count());
        $this->assertSame('No work permits need attention now.', Notification::first()->message);
    }

    public function test_a_dry_run_saves_nothing_and_the_command_is_scheduled_daily(): void
    {
        $this->permit('Tomorrow', '2026-11-19');

        $this->artisan('hr:check-expiring-permits --dry-run')->assertSuccessful();

        $this->assertSame(0, Notification::count());
        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->contains(fn ($event) => str_contains($event->command, 'hr:check-expiring-permits'));
        $this->assertTrue($scheduled);
    }
}
