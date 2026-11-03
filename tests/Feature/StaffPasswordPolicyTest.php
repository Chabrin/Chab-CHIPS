<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use App\Services\StaffPasswordPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffPasswordPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'password_test', 'database.connections.password_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(8, 0));
        Schema::create('josya_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->boolean('block')->default(false);
            $table->boolean('requireReset')->default(false);
            $table->string('remember_token')->nullable();
            $table->string('reset_token')->nullable();
            $table->timestamp('reset_token_expires_at')->nullable();
        });
        Schema::create('sys_user_staff_links', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('staff_id');
        });
        Schema::create('sys_staff', fn (Blueprint $table) => $table->id());
        Schema::create('cb_roles', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('display_name')->nullable();
        });
        Schema::create('cb_role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('role_id'); $table->string('user_type');
        });
        Schema::create('cb_sub_modules', function (Blueprint $table) {
            $table->id(); $table->string('sub_module_url'); $table->integer('order');
        });
        Schema::create('cb_sub_module_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_module_id'); $table->unsignedBigInteger('role_id');
        });
        Schema::create('cb_modules', function (Blueprint $table) {
            $table->id(); $table->integer('module_order');
        });
        Schema::create('cb_super_modules', function (Blueprint $table) {
            $table->id(); $table->integer('order');
        });
        DB::table('cb_roles')->insert(['id' => 1, 'name' => 'staff', 'display_name' => 'Staff']);
        (require database_path('migrations/2026_10_10_000003_create_password_securities_table.php'))->up();
        (require database_path('migrations/2026_01_23_115917_create_notifications_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_add_staff_password_history_and_reminders.php'))->up();
    }

    private function staff(): User
    {
        $user = User::create(['name' => 'Test Staff', 'username' => 'teststaff', 'email' => 'staff@example.test', 'password' => 'FirstPassword123!', 'staff_id' => 10]);
        DB::table('cb_role_user')->insert(['user_id' => $user->id, 'role_id' => 1, 'user_type' => User::class]);
        return $user;
    }

    public function test_expiry_at_exactly_thirty_days_and_change_restarts_period(): void
    {
        $user = $this->staff();
        $policy = app(StaffPasswordPolicy::class);
        $expiry = $policy->expiresAt($user);
        $this->travelTo($expiry->copy()->subSecond());
        $this->assertFalse($policy->requiresChange($user));
        $this->travelTo($expiry);
        $this->assertTrue($policy->requiresChange($user));
        $user->password = 'SecondPassword123!';
        $user->save();
        $this->assertFalse($policy->requiresChange($user));
        $this->assertEquals(now()->addDays(30), $policy->expiresAt($user));
    }

    public function test_all_previous_passwords_are_rejected_without_changing_saved_password(): void
    {
        $user = $this->staff();
        foreach (['SecondPassword123!', 'ThirdPassword123!'] as $password) {
            $user->password = $password;
            $user->save();
        }
        foreach (['FirstPassword123!', 'SecondPassword123!', 'ThirdPassword123!'] as $password) {
            $user = $user->fresh();
            try {
                $user->password = $password;
                $user->save();
                $this->fail('Reused password accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('password', $exception->errors());
            }
            $this->assertTrue(Hash::check('ThirdPassword123!', $user->fresh()->password));
        }
        $this->assertSame(3, DB::table('password_histories')->count());
    }

    public function test_link_table_staff_are_covered_but_nonstaff_do_not_expire(): void
    {
        $user = User::create(['password' => 'FirstPassword123!']);
        $this->travel(31)->days();
        $policy = app(StaffPasswordPolicy::class);
        $this->assertFalse($policy->requiresChange($user));
        DB::table('sys_user_staff_links')->insert(['user_id' => $user->id, 'staff_id' => 20]);
        $this->assertTrue($policy->requiresChange($user));
    }

    public function test_activation_preserves_known_dates_and_grants_grace_for_unknown_dates(): void
    {
        $migration = require database_path('migrations/2026_11_03_000001_add_staff_password_history_and_reminders.php');
        $migration->down();
        $hash = Hash::make('ExistingPassword123!');
        DB::table('josya_users')->insert([
            ['id' => 1, 'password' => $hash, 'staff_id' => 1],
            ['id' => 2, 'password' => $hash, 'staff_id' => 2],
        ]);
        DB::table('password_securities')->insert([
            'user_id' => 1, 'password_changed_at' => now()->subDays(20),
        ]);
        $migration->up();
        $policy = app(StaffPasswordPolicy::class);
        $this->assertEquals(now()->addDays(10), $policy->expiresAt(User::find(1)));
        $this->assertEquals(now()->addDays(30), $policy->expiresAt(User::find(2)));
        $this->assertSame(2, DB::table('password_histories')->count());
    }

    public function test_password_does_not_change_when_history_cannot_be_saved(): void
    {
        $user = $this->staff();
        DB::statement("CREATE TRIGGER reject_password_history BEFORE INSERT ON password_histories BEGIN SELECT RAISE(ABORT, 'history unavailable'); END");
        try {
            $user->password = 'SecondPassword123!';
            $user->save();
            $this->fail('A password change without history must fail');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('history unavailable', $exception->getMessage());
        }
        $this->assertTrue(Hash::check('FirstPassword123!', User::find($user->id)->password));
    }

    public function test_expired_sessions_are_blocked_and_password_page_and_logout_are_accessible(): void
    {
        $user = $this->staff();
        Route::middleware(['web', 'auth'])->get('/password-policy-test', fn () => 'protected');
        $this->travel(30)->days();
        $this->actingAs($user)->get('/password-policy-test')->assertRedirect(route('user.profile', ['tab' => 'security']));
        $this->getJson('/password-policy-test')->assertForbidden()->assertJsonStructure(['redirect']);
        $this->get(route('staff.password.edit'))->assertRedirect(route('user.profile', ['tab' => 'security']));
        $this->get(route('user.profile'))->assertOk()->assertViewIs('auth.profile')
            ->assertViewHas('showSecurity', true)->assertViewHas('passwordChangeRequired', true)
            ->assertSee('Please change it below before continuing.')
            ->assertDontSee('id="forcePasswordChangeModal"', false);
        $this->postJson(route('user.password.update'), [
            'current_password' => 'FirstPassword123!', 'new_password' => 'SecondPassword123!',
            'new_password_confirmation' => 'SecondPassword123!',
        ])->assertOk()->assertJson(['success' => true]);
        $this->get(route('user.profile', ['tab' => 'security']))->assertOk()->assertViewHas('passwordChangeRequired', false);
        $this->get('/password-policy-test')->assertOk();
    }

    public function test_legacy_password_submission_returns_to_my_account(): void
    {
        $user = $this->staff();
        $this->actingAs($user);
        $this->post(route('staff.password.update'), [
            'current_password' => 'FirstPassword123!', 'password' => 'SecondPassword123!',
            'password_confirmation' => 'SecondPassword123!',
        ])->assertRedirect(route('user.profile', ['tab' => 'security']));
    }

    public function test_my_account_security_tab_is_available_before_expiry_without_a_separate_menu_item(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->get(route('user.profile'))->assertOk()->assertViewHas('showSecurity', false);
        $response = $this->get(route('user.profile', ['tab' => 'security']))
            ->assertOk()->assertViewHas('showSecurity', true)->assertViewHas('passwordChangeRequired', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="passwordChangeForm"'));
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $menuLinks = (new \DOMXPath($document))->query('//*[@id="account-menu"]//a[contains(., "Change Password")]');
        $this->assertSame(0, $menuLinks->length);

        $user->requireReset = true;
        $user->save();
        $response = $this->get(route('user.profile'))->assertOk()->assertViewHas('showSecurity', true)
            ->assertViewHas('passwordChangeRequired', true)->assertDontSee('id="forcePasswordChangeModal"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="passwordChangeForm"'));
    }

    public function test_profile_and_forgot_password_paths_report_history_errors(): void
    {
        $user = $this->staff();
        $user->password = 'SecondPassword123!';
        $user->save();
        $this->actingAs($user);
        $response = app(LoginController::class)->updatePassword(Request::create('/', 'POST', [
            'current_password' => 'SecondPassword123!', 'new_password' => 'FirstPassword123!',
            'new_password_confirmation' => 'FirstPassword123!',
        ]));
        $this->assertSame(422, $response->status());
        $this->assertArrayHasKey('new_password', $response->getData(true)['errors']);
        $user = $user->fresh();
        $user->reset_token = Hash::make('test-token');
        $user->reset_token_expires_at = now()->addHour();
        $user->save();
        $response = app(AuthController::class)->resetPassword(Request::create('/', 'POST', [
            'token' => 'test-token', 'email' => $user->email, 'password' => 'FirstPassword123!',
            'password_confirmation' => 'FirstPassword123!',
        ]));
        $this->assertSame('error', $response->getData(true)['status']);
        $this->assertStringContainsString('used this password before', $response->getData(true)['message']);
        $this->assertNotNull($user->fresh()->reset_token);
    }

    public function test_reminders_are_personal_deduplicated_and_restart_after_password_change(): void
    {
        $user = $this->staff();
        User::create(['password' => 'OtherPassword123!']);
        $start = now();
        $this->travelTo($start->copy()->addDays(23));
        $this->artisan('staff:password-reminders')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('password_expiry_reminders')->count());
        foreach ([27 => 3, 29 => 1] as $elapsed => $days) {
            $this->travelTo($start->copy()->addDays($elapsed));
            $this->artisan('staff:password-reminders')->assertSuccessful();
            $this->artisan('staff:password-reminders')->assertSuccessful();
            $notification = DB::table('notifications')->latest('id')->first();
            $this->assertSame($user->id, $notification->notifiable_id);
            $this->assertSame($days, json_decode($notification->data, true)['days_before']);
            $this->assertSame('/user/profile?tab=security', $notification->action_url);
        }
        $this->assertSame(2, DB::table('notifications')->count());
        $user->password = 'SecondPassword123!';
        $user->save();
        $this->artisan('staff:password-reminders')->assertSuccessful();
        $this->assertSame(2, DB::table('notifications')->count());
        $this->travel(27)->days();
        $this->artisan('staff:password-reminders')->assertSuccessful();
        $this->assertSame(3, DB::table('notifications')->count());
    }
}
