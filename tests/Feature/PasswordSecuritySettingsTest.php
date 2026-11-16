<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SystemControlController;
use App\Models\SystemControl;
use App\Models\User;
use App\Services\PasswordSecuritySettings;
use App\Services\StaffPasswordPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The Password Security settings on System Controls: the switch, every number and rule, and how bad values are handled.
 */
class PasswordSecuritySettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'password_settings_test', 'database.connections.password_settings_test' => [
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
        (require database_path('migrations/2026_10_01_000001_create_control_categories_table.php'))->up();
        Schema::create('cb_system_configs', function (Blueprint $table) {
            $table->increments('id'); $table->integer('category_id'); $table->string('display_name', 50); $table->integer('precedence')->nullable();
            $table->text('value'); $table->string('setting', 50); $table->integer('toggle_boolean')->default(0); $table->timestamps();
        });
        (require database_path('migrations/2026_11_16_000001_add_password_security_settings.php'))->up();
    }

    private function set(string $key, string $value): void
    {
        DB::table('cb_system_configs')->where('setting', $key)->update(['value' => $value]);
    }

    private function on(): void
    {
        $this->set('password_security_enabled', '1');
    }

    private function staff(string $password = 'FirstPassword123!'): User
    {
        $user = User::create(['name' => 'Test Staff', 'username' => 'teststaff', 'email' => 'staff@example.test', 'password' => $password, 'staff_id' => 10]);
        DB::table('cb_role_user')->insert(['user_id' => $user->id, 'role_id' => 1, 'user_type' => User::class]);
        return $user;
    }

    private function change(User $user, string $password): User
    {
        $user = $user->fresh();
        $user->password = $password;
        $user->save();
        return $user->fresh();
    }

    public function test_migration_adds_the_password_security_tab_switched_off_and_never_overwrites_changes(): void
    {
        $category = DB::table('control_categories')->where('category_name', 'password_security')->first();
        $this->assertSame('Password Security', $category->display_name);
        $rows = DB::table('cb_system_configs')->where('category_id', $category->id)->orderBy('precedence')->pluck('value', 'setting')->all();
        $this->assertSame([
            'password_security_enabled' => '0', 'password_expiry_days' => '30', 'password_reminder_days' => '3,1',
            'password_min_length' => '8', 'password_require_complexity' => '1', 'password_history_count' => '0',
            'password_block_similar' => '1',
        ], $rows);
        $this->assertSame([1, 0, 0, 0, 1, 0, 1], DB::table('cb_system_configs')->orderBy('precedence')->pluck('toggle_boolean')->all());

        $this->set('password_expiry_days', '45');
        (require database_path('migrations/2026_11_16_000001_add_password_security_settings.php'))->up();
        $this->assertSame(7, DB::table('cb_system_configs')->count());
        $this->assertSame(1, DB::table('control_categories')->where('category_name', 'password_security')->count());
        $this->assertSame('45', DB::table('cb_system_configs')->where('setting', 'password_expiry_days')->value('value'));
    }

    public function test_nothing_is_enforced_while_the_switch_is_off(): void
    {
        $user = $this->staff();
        Route::middleware(['web', 'auth'])->get('/password-settings-test', fn () => 'protected');
        $policy = app(StaffPasswordPolicy::class);

        $this->travel(40)->days();
        $this->assertFalse($policy->requiresChange($user));
        $this->actingAs($user)->get('/password-settings-test')->assertOk();
        $this->artisan('staff:password-reminders')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(['min:8'], $policy->passwordRules(8));
        $this->assertSame(['min:10'], $policy->passwordRules(10));

        $user = $this->change($user, 'FirstPassword123!');
        $this->assertTrue(Hash::check('FirstPassword123!', $user->password));
        $this->assertSame(2, DB::table('password_histories')->count(), 'changes are still recorded while the switch is off');
        $policy->assertNotSimilar($user, 'Summer2025!', 'Summer2026!');
    }

    public function test_switching_on_enforces_the_expiry_and_switching_off_lifts_it(): void
    {
        $user = $this->staff();
        $policy = app(StaffPasswordPolicy::class);
        $this->travel(31)->days();
        $this->assertFalse($policy->requiresChange($user));
        $this->on();
        $this->assertTrue(app(StaffPasswordPolicy::class)->requiresChange($user));
        $this->set('password_security_enabled', '0');
        $this->assertFalse(app(StaffPasswordPolicy::class)->requiresChange($user));
    }

    public function test_expiry_days_setting_is_used(): void
    {
        $this->on();
        $this->set('password_expiry_days', '10');
        $user = $this->staff();
        $policy = app(StaffPasswordPolicy::class);
        $this->assertEquals(now()->addDays(10), $policy->expiresAt($user));
        $this->travel(9)->days();
        $this->assertFalse($policy->requiresChange($user));
        $this->travel(1)->days();
        $this->assertTrue($policy->requiresChange($user));
    }

    public function test_reminder_days_setting_is_used_and_an_empty_list_sends_none(): void
    {
        $this->on();
        $this->set('password_reminder_days', '7, 2');
        $user = $this->staff();
        $start = now();
        foreach ([22 => false, 23 => 7, 27 => false, 28 => 2] as $elapsed => $expected) {
            $this->travelTo($start->copy()->addDays($elapsed));
            $this->artisan('staff:password-reminders')->assertSuccessful();
            $latest = DB::table('notifications')->latest('id')->first();
            if ($expected === false) {
                $this->assertSame($elapsed === 22 ? 0 : 1, DB::table('notifications')->count());
                continue;
            }
            $this->assertSame($user->id, $latest->notifiable_id);
            $this->assertSame($expected, json_decode($latest->data, true)['days_before']);
        }

        $this->change($user, 'SecondPassword123!');
        $this->set('password_reminder_days', '');
        $this->travelTo(now()->addDays(27));
        $before = DB::table('notifications')->count();
        $this->artisan('staff:password-reminders')->assertSuccessful();
        $this->assertSame($before, DB::table('notifications')->count());
    }

    public function test_history_count_limits_how_far_back_reuse_is_blocked(): void
    {
        $this->on();
        $user = $this->staff('PasswordOne123!');
        $user = $this->change($user, 'PasswordTwo456!');
        $user = $this->change($user, 'PasswordThree789!');

        $this->expectReuseRejected($user, 'PasswordOne123!', 'blocked while every past password counts');

        $this->set('password_history_count', '2');
        $this->expectReuseRejected($user, 'PasswordTwo456!', 'second most recent is still blocked');
        $this->expectReuseRejected($user, 'PasswordThree789!', 'the current password is always blocked');
        $user = $this->change($user, 'PasswordOne123!');
        $this->assertTrue(Hash::check('PasswordOne123!', $user->password), 'older than the last 2, so allowed');
    }

    private function expectReuseRejected(User $user, string $password, string $why): void
    {
        try {
            $user = $user->fresh();
            $user->password = $password;
            $user->save();
            $this->fail('Accepted: ' . $why);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors(), $why);
        }
    }

    public function test_length_and_complexity_rules_follow_the_settings(): void
    {
        $this->on();
        $this->set('password_min_length', '12');
        $policy = app(StaffPasswordPolicy::class);
        $fails = fn (string $password) => Validator::make(['password' => $password], ['password' => app(StaffPasswordPolicy::class)->passwordRules(8)])->fails();

        $this->assertTrue($fails('Short1aA'), 'under the configured length');
        $this->assertTrue($fails('alllowercase12345'), 'no upper case');
        $this->assertTrue($fails('NoNumbersInHereAtAll'), 'no number');
        $this->assertFalse($fails('LongEnough123A'));

        $this->set('password_require_complexity', '0');
        $this->assertFalse($fails('alllowercasewords'));
        $this->assertTrue($fails('short'));
        $this->assertSame(12, app(PasswordSecuritySettings::class)->minLength(8));
    }

    public function test_a_password_similar_to_the_current_one_is_refused_only_when_the_setting_is_on(): void
    {
        $this->on();
        $user = $this->staff('Summer2025!');
        $policy = app(StaffPasswordPolicy::class);
        foreach (['Summer2026!', 'summer2025', 'Summer2025!!', 'Summer20256', 'Summer'] as $similar) {
            try {
                $policy->assertNotSimilar($user, 'Summer2025!', $similar, 'new_password');
                $this->fail("$similar was accepted");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('new_password', $exception->errors());
            }
        }
        $policy->assertNotSimilar($user, 'Summer2025!', 'Harvest#Moon77');
        $policy->assertNotSimilar($user, 'Summer2025!', 'Winter9999!x');

        $this->set('password_block_similar', '0');
        app(StaffPasswordPolicy::class)->assertNotSimilar($user, 'Summer2025!', 'Summer2026!');

        $this->set('password_block_similar', '1');
        $other = User::create(['password' => 'Summer2025!']);
        app(StaffPasswordPolicy::class)->assertNotSimilar($other, 'Summer2025!', 'Summer2026!');
    }

    public function test_my_account_change_uses_the_configured_rules(): void
    {
        $this->on();
        $this->set('password_min_length', '12');
        $user = $this->staff('Summer2025!');
        $this->actingAs($user);
        $request = fn (string $new) => Request::create('/', 'POST', [
            'current_password' => 'Summer2025!', 'new_password' => $new, 'new_password_confirmation' => $new,
        ]);

        $short = app(LoginController::class)->updatePassword($request('Aa1short'));
        $this->assertSame(422, $short->status());
        $this->assertArrayHasKey('new_password', $short->getData(true)['errors']);

        $similar = app(LoginController::class)->updatePassword($request('Summer2026!!'));
        $this->assertSame(422, $similar->status());
        $this->assertStringContainsString('too similar', $similar->getData(true)['errors']['new_password'][0]);

        $this->assertSame(200, app(LoginController::class)->updatePassword($request('Harvest#Moon7788'))->status());
    }

    public function test_my_account_page_shows_no_password_rules_while_the_switch_is_off(): void
    {
        $this->set('password_expiry_days', '45');
        $this->set('password_min_length', '14');
        $this->actingAs($this->staff())->get(route('user.profile', ['tab' => 'security']))
            ->assertOk()->assertDontSee('expire every')->assertDontSee('At least 14 characters')->assertSee('minlength="8"', false);
    }

    public function test_my_account_page_shows_the_configured_expiry_and_length(): void
    {
        $this->set('password_expiry_days', '45');
        $this->set('password_min_length', '14');
        $this->on();
        $this->actingAs($this->staff())->get(route('user.profile', ['tab' => 'security']))
            ->assertOk()->assertSee('expire every 45 days')->assertSee('minlength="14"', false)
            ->assertSee('At least 14 characters, with upper and lower case letters and a number.');
    }

    public function test_unreadable_values_fall_back_to_the_defaults(): void
    {
        $settings = fn () => app(PasswordSecuritySettings::class);
        foreach (['abc', '0', '999', '', '-5', '1.5'] as $bad) {
            $this->set('password_expiry_days', $bad);
            $this->assertSame(30, $settings()->expiryDays(), "expiry '$bad'");
        }
        foreach (['3', '7', '65', 'abc'] as $bad) {
            $this->set('password_min_length', $bad);
            $this->set('password_security_enabled', '1');
            $this->assertSame(8, $settings()->minLength(10), "length '$bad'");
        }
        $this->set('password_history_count', '51');
        $this->assertSame(0, $settings()->historyCount());
        $this->set('password_reminder_days', 'x,3');
        $this->assertSame([3, 1], $settings()->reminderDays());
        $this->set('password_reminder_days', '1,2,3,4,5,6');
        $this->assertSame([3, 1], $settings()->reminderDays());
        $this->set('password_reminder_days', ' 1,7 , 3 ');
        $this->assertSame([7, 3, 1], $settings()->reminderDays());
        $this->set('password_reminder_days', '7,40');
        $this->set('password_expiry_days', '30');
        $this->assertSame([7], $settings()->reminderDays());
        $this->set('password_require_complexity', 'maybe');
        $this->assertTrue($settings()->requireComplexity());
        $this->set('password_security_enabled', 'junk');
        $this->assertFalse($settings()->enabled());

        DB::table('cb_system_configs')->delete();
        $this->assertFalse($settings()->enabled());
        $this->assertSame(30, $settings()->expiryDays());
        $this->assertSame([3, 1], $settings()->reminderDays());
    }

    public function test_the_settings_page_rejects_wrong_values_and_saves_good_ones(): void
    {
        $id = fn (string $key) => DB::table('cb_system_configs')->where('setting', $key)->value('id');
        $controller = app(SystemControllerProbe::class);

        $bad = $controller->save([['id' => $id('password_expiry_days'), 'value' => '0'], ['id' => $id('password_min_length'), 'value' => '5']]);
        $this->assertSame(422, $bad->status());
        $this->assertStringContainsString('Password expires after (days) must be a whole number from 1 to 365.', $bad->getData(true)['error']);
        $this->assertStringContainsString('Minimum password length must be a whole number from 8 to 64.', $bad->getData(true)['error']);
        $this->assertSame('30', DB::table('cb_system_configs')->where('setting', 'password_expiry_days')->value('value'), 'nothing saved when one value is wrong');

        $this->assertSame(422, $controller->save([['id' => $id('password_reminder_days'), 'value' => '7;3']])->status());

        $good = $controller->save([
            ['id' => $id('password_expiry_days'), 'value' => '60'], ['id' => $id('password_security_enabled'), 'toggle' => 1, 'value' => null],
        ]);
        $this->assertSame(200, $good->status());
        $this->assertSame('60', DB::table('cb_system_configs')->where('setting', 'password_expiry_days')->value('value'));
        $this->assertTrue(app(PasswordSecuritySettings::class)->enabled());
        $this->assertSame(60, app(PasswordSecuritySettings::class)->expiryDays());

        $this->assertNull(PasswordSecuritySettings::validate('lockout_attempts', 'anything'));
        $this->assertNotNull(PasswordSecuritySettings::validate('password_block_similar', 'maybe'));
        $this->assertNull(PasswordSecuritySettings::validate('password_reminder_days', ''));
    }
}

/**
 * Calls the real bulk-save action of the System Controls page without the audit observers, which need their own tables.
 */
class SystemControllerProbe extends SystemControlController
{
    public function save(array $bulk)
    {
        return SystemControl::withoutEvents(fn () => $this->ajaxStoreControl(Request::create('/', 'POST', ['bulk' => json_encode($bulk)])));
    }
}
