<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Services\EmployeeContractExpiry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeContractExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Model::unsetEventDispatcher();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-27 14:00:00', 'Africa/Nairobi'));
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id(); $t->string('full_names'); $t->integer('current_status_id');
        });
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id(); $t->integer('staff_id'); $t->dateTime('date_start')->nullable();
            $t->dateTime('date_end')->nullable(); $t->integer('current_status_id');
        });
        Schema::create('josya_users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->integer('block')->default(0);
        });
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('cb_role_user', function (Blueprint $t) {
            $t->integer('user_id'); $t->integer('role_id'); $t->string('user_type');
        });
        (require database_path('migrations/2026_01_23_115917_create_notifications_table.php'))->up();
        (require database_path('migrations/2026_10_27_000001_create_employee_contract_reminders_table.php'))->up();
        DB::table('sys_staff')->insert(['id' => 1, 'full_names' => 'Test Employee', 'current_status_id' => 1]);
        DB::table('josya_users')->insert(['id' => 1, 'name' => 'Test HR', 'block' => 0]);
        DB::table('cb_roles')->insert(['id' => 1, 'name' => 'hr_manager']);
        DB::table('cb_role_user')->insert(['user_id' => 1, 'role_id' => 1, 'user_type' => User::class]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function contract(?string $end, ?string $start = '2026-01-01', int $status = 5): Contract
    {
        return Contract::create(['staff_id' => 1, 'date_start' => $start, 'date_end' => $end, 'current_status_id' => $status]);
    }

    public function test_date_boundaries_missing_dates_and_upcoming_contracts(): void
    {
        $expiry = app(EmployeeContractExpiry::class);
        foreach ([
            ['2026-11-27', '2026-01-01', 'active', 31],
            ['2026-11-26', '2026-01-01', 'expiring_soon', 30],
            ['2026-10-27', '2026-01-01', 'expiring_soon', 0],
            ['2026-10-26', '2026-01-01', 'expired', -1],
            ['2026-12-01', '2026-11-01', 'upcoming', 35],
            [null, '2026-01-01', 'missing_dates', null],
            ['2026-11-01', null, 'missing_dates', null],
            ['2026-01-01', '2026-11-01', 'invalid_dates', -299],
        ] as [$end, $start, $status, $days]) {
            $tracking = $expiry->tracking(new Contract(['date_start' => $start, 'date_end' => $end]));
            $this->assertSame($status, $tracking['status']);
            if ($status !== 'invalid_dates') $this->assertSame($days, $tracking['days_remaining']);
        }
    }

    public function test_daily_check_creates_one_in_system_reminder_and_rerun_does_not_repeat_it(): void
    {
        $contract = $this->contract('2026-11-10');
        $this->artisan('hr:check-expiring-contracts')->expectsOutput('Created 1 employee contract reminder(s).')->assertSuccessful();
        $this->artisan('hr:check-expiring-contracts')->expectsOutput('Created 0 employee contract reminder(s).')->assertSuccessful();
        $this->assertSame(1, DB::table('notifications')->count());
        $notification = DB::table('notifications')->first();
        $this->assertSame(User::class, $notification->notifiable_type);
        $this->assertStringContainsString('expires in 14 days', $notification->message);
        $this->assertStringContainsString('contract_id=' . $contract->id, $notification->action_url);
        $this->assertSame(5, $contract->fresh()->current_status_id);
    }

    public function test_preview_writes_nothing(): void
    {
        $this->contract('2026-11-03');
        $this->artisan('hr:check-expiring-contracts', ['--dry-run' => true])->expectsOutput('Would create 1 reminder(s). No changes made.')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('employee_contract_reminders')->count());
    }

    public function test_next_window_and_changed_expiry_date_allow_new_reminders(): void
    {
        $contract = $this->contract('2026-11-10');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-03 14:00', 'Africa/Nairobi'));
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $contract->update(['date_end' => '2026-11-17']);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(3, DB::table('notifications')->count());
    }

    public function test_inactive_staff_and_unapproved_contracts_are_excluded(): void
    {
        $this->contract('2026-11-03', status: 1);
        $this->contract(null);
        $this->contract('2026-11-03');
        DB::table('sys_staff')->update(['current_status_id' => 2]);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_started_renewal_supersedes_old_contract_but_future_renewal_does_not(): void
    {
        $this->contract('2026-10-26');
        $renewal = $this->contract('2027-10-26', '2026-10-27');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
        $renewal->update(['date_start' => '2026-10-28']);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertStringContainsString('expired 1 day ago', DB::table('notifications')->value('message'));
    }

    public function test_blocked_or_non_hr_recipients_are_excluded(): void
    {
        $this->contract('2026-11-03');
        DB::table('josya_users')->update(['block' => 1]);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        DB::table('josya_users')->update(['block' => 0]);
        DB::table('cb_roles')->update(['name' => 'accountant']);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_expiry_day_is_not_overdue_and_deleted_alert_is_not_repeated(): void
    {
        $this->contract('2026-10-27');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertStringContainsString('expires today', DB::table('notifications')->value('message'));
        DB::table('notifications')->delete();
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_missed_day_catches_up_in_current_reminder_window(): void
    {
        $this->contract('2026-11-08');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(14, DB::table('employee_contract_reminders')->value('days_before'));
        $this->assertStringContainsString('expires in 12 days', DB::table('notifications')->value('message'));
    }

    public function test_daily_check_is_registered_in_the_active_scheduler(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events())->filter(fn ($event) => str_contains($event->command ?? '', 'hr:check-expiring-contracts'));
        $this->assertCount(1, $events);
        $this->assertSame('0 8 * * *', $events->first()->expression);
        $this->assertSame('Africa/Nairobi', $events->first()->timezone);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    public function test_directors_and_hr_managers_receive_alerts_without_duplicate_role_deliveries(): void
    {
        $this->contract('2026-11-03');
        DB::table('cb_roles')->insert(['id' => 2, 'name' => 'directors']);
        DB::table('josya_users')->insert(['id' => 2, 'name' => 'Test Director', 'block' => 0]);
        DB::table('cb_role_user')->insert([
            ['user_id' => 2, 'role_id' => 2, 'user_type' => User::class],
            ['user_id' => 1, 'role_id' => 2, 'user_type' => User::class],
        ]);

        $this->artisan('hr:check-expiring-contracts')->expectsOutput('Created 2 employee contract reminder(s).')->assertSuccessful();
        $this->assertSame([1, 2], DB::table('notifications')->orderBy('notifiable_id')->pluck('notifiable_id')->all());
        $this->artisan('hr:check-expiring-contracts')->expectsOutput('Created 0 employee contract reminder(s).')->assertSuccessful();
    }

    public function test_a_blocked_director_does_not_receive_alerts(): void
    {
        $this->contract('2026-11-03');
        DB::table('cb_roles')->update(['name' => 'directors']);
        DB::table('josya_users')->update(['block' => 1]);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
    }
}
