<?php

namespace Tests\Feature;

use App\Mail\SetPasswordMail;
use App\Models\User;
use App\Services\PasswordSetupLink;
use App\Services\SetupPasswordRules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A new system user is emailed a one-time link to choose their own password. Nobody else sets or sees it: the account starts with
 * a password nobody knows, the link is only ever emailed, works once, lasts 48 hours and is replaced by a newer one.
 */
class PasswordSetupLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'setup_test', 'database.connections.setup_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(9, 0));

        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            foreach (['name', 'username', 'email', 'password', 'activation', 'params', 'otpKey', 'otep', 'lastResetTime', 'reset_token', 'reset_token_expires_at', 'registerDate', 'lastvisitDate', 'remember_token'] as $column) {
                $t->string($column)->nullable();
            }
            foreach (['block', 'sendEmail', 'resetCount', 'requireReset', 'staff_id', 'is_field_officer'] as $column) {
                $t->integer($column)->nullable();
            }
        });
        (require database_path('migrations/2026_11_18_100000_add_password_setup_to_josya_users.php'))->up();
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('full_names')->nullable();
            $t->string('staff_number')->nullable();
        });
        Schema::create('sys_user_staff_links', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('staff_id'); });
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('display_name')->nullable(); });
        Schema::create('cb_role_user', function (Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->string('user_type')->nullable(); });
        (require database_path('migrations/2026_10_10_000003_create_password_securities_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_add_staff_password_history_and_reminders.php'))->up();
        (require database_path('migrations/2026_10_01_000001_create_control_categories_table.php'))->up();
        Schema::create('cb_system_configs', function (Blueprint $t) {
            $t->increments('id'); $t->integer('category_id'); $t->string('display_name', 50); $t->integer('precedence')->nullable();
            $t->text('value'); $t->string('setting', 50); $t->integer('toggle_boolean')->default(0); $t->timestamps();
        });
        (require database_path('migrations/2026_11_16_000001_add_password_security_settings.php'))->up();
        DB::table('cb_roles')->insert(['id' => 1, 'name' => 'hr_manager', 'display_name' => 'HR Manager']);

        Mail::fake();
    }

    private function user(array $overrides = []): User
    {
        $user = User::create($overrides + [
            'name' => 'Lucy Karanja', 'username' => 'lucy.k', 'email' => 'lucy.k@chabrinagencies.co.ke',
            'password' => PasswordSetupLink::unusablePassword(), 'block' => 0, 'sendEmail' => 0, 'activation' => '', 'params' => '',
            'resetCount' => 0, 'otpKey' => '', 'otep' => '', 'requireReset' => 0,
        ]);

        return $user->fresh();
    }

    /** The secret in the link of the latest email sent. */
    private function lastToken(): string
    {
        $mail = Mail::sent(SetPasswordMail::class)->last();
        $this->assertNotNull($mail, 'No password link email was sent.');

        return basename(parse_url($mail->url, PHP_URL_PATH));
    }

    private function links(): PasswordSetupLink
    {
        return app(PasswordSetupLink::class);
    }

    private function setPassword(string $token, string $password, ?string $confirmation = null)
    {
        return $this->postJson(route('password.setup.store', ['token' => $token]), [
            'password' => $password, 'password_confirmation' => $confirmation ?? $password,
        ]);
    }

    // ---- the link itself --------------------------------------------------------------------------------------------------

    public function test_a_new_link_is_kept_only_as_a_hash_and_lasts_48_hours(): void
    {
        $user = $this->user();
        $token = $this->links()->issue($user);

        $row = DB::table('josya_users')->where('id', $user->id)->first();
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $row->password_setup_token);
        $this->assertNotSame($token, $row->password_setup_token);
        $this->assertSame(now()->addHours(48)->timestamp, \Carbon\Carbon::parse($row->password_setup_expires_at)->timestamp);
        $this->assertNull($row->password_setup_used_at);
    }

    public function test_a_newer_link_cancels_the_older_one(): void
    {
        $user = $this->user();
        $old = $this->links()->issue($user);
        $new = $this->links()->issue($user);

        $this->assertNull($this->links()->find($old));
        $this->assertSame($user->id, $this->links()->find($new)->id);
    }

    public function test_sending_emails_the_link_to_the_user_and_never_a_password(): void
    {
        $user = $this->user();

        $this->assertTrue($this->links()->send($user));

        Mail::assertSent(SetPasswordMail::class, 1);
        Mail::assertSent(SetPasswordMail::class, fn (SetPasswordMail $mail) => $mail->hasTo('lucy.k@chabrinagencies.co.ke'));
        $html = Mail::sent(SetPasswordMail::class)->first()->render();
        $this->assertStringContainsString('/set-password/' . $this->lastToken(), $html);
        $this->assertStringContainsString('48 hours', $html);
        $this->assertStringNotContainsStringIgnoringCase('temporary password', $html);
        $this->assertNotNull($user->fresh()->password_setup_sent_at);
        $this->assertSame(PasswordSetupLink::WAITING, $this->links()->stateOf($user->fresh()));
    }

    public function test_an_email_that_cannot_be_sent_is_reported_and_never_thrown(): void
    {
        Mail::swap(new class {
            public function to(...$arguments)
            {
                throw new \RuntimeException('smtp is down');
            }
        });
        $user = $this->user();

        $this->assertFalse($this->links()->send($user));

        $row = DB::table('josya_users')->where('id', $user->id)->first();
        $this->assertNull($row->password_setup_sent_at, 'A link that was never emailed is not marked as sent');
        $this->assertNotNull($row->password_setup_token, 'The link stays so Resend can replace it');
    }

    public function test_the_state_reads_none_waiting_and_expired(): void
    {
        $this->assertSame('none', PasswordSetupLink::state(null, null, null));
        $this->assertSame('none', PasswordSetupLink::state('hash', now()->addHour(), now()), 'A used link is no longer waiting');
        $this->assertSame('waiting', PasswordSetupLink::state('hash', now()->addHour(), null));
        $this->assertSame('expired', PasswordSetupLink::state('hash', now()->subMinute(), null));
    }

    public function test_the_account_cannot_be_signed_in_to_before_the_person_sets_a_password(): void
    {
        $user = $this->user();
        $this->links()->send($user);

        foreach (['password', 'Admin123456!', ''] as $guess) {
            $this->assertFalse(Auth::validate(['email' => $user->email, 'password' => $guess]), "Guess '$guess' must not work");
        }
    }

    // ---- the page ---------------------------------------------------------------------------------------------------------

    public function test_a_good_link_opens_the_page_with_the_login_email_and_the_minimum_length(): void
    {
        $user = $this->user();
        $this->links()->send($user);

        $this->get(route('password.setup', ['token' => $this->lastToken()]))
            ->assertOk()
            ->assertSee('lucy.k@chabrinagencies.co.ke')
            ->assertSee('At least 12 characters');
    }

    public function test_a_link_we_never_made_is_refused(): void
    {
        $this->get(route('password.setup', ['token' => str_repeat('a', 64)]))->assertNotFound()->assertSee('not valid');
        $this->get('/set-password/short')->assertNotFound();
    }

    public function test_an_expired_link_says_so_and_offers_a_new_one(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();

        $this->travel(49)->hours();

        $this->get(route('password.setup', ['token' => $token]))
            ->assertStatus(410)
            ->assertSee('expired')
            ->assertSee('Send me a new link');
    }

    public function test_a_used_link_says_so(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();
        $this->setPassword($token, 'orange river keeps flowing north')->assertOk();

        $this->get(route('password.setup', ['token' => $token]))->assertStatus(410)->assertSee('already been used');
    }

    public function test_a_suspended_account_cannot_use_its_link(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();
        User::whereKey($user->id)->update(['block' => 1]);

        $this->get(route('password.setup', ['token' => $token]))->assertStatus(410)->assertSee('suspended');
        $this->setPassword($token, 'orange river keeps flowing north')->assertStatus(410);
    }

    // ---- choosing the password --------------------------------------------------------------------------------------------

    public function test_the_person_sets_their_own_password_and_the_link_is_used_up(): void
    {
        $user = $this->user(['requireReset' => 1]);
        $this->links()->send($user);
        $token = $this->lastToken();

        $this->setPassword($token, 'orange river keeps flowing north')
            ->assertOk()->assertJson(['status' => 'success', 'redirect' => route('login')]);

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('orange river keeps flowing north', $fresh->password));
        $this->assertFalse((bool) $fresh->requireReset, 'They chose it themselves, so nothing is forced to change on first login');
        $this->assertNotNull($fresh->password_setup_used_at);
        $this->assertSame(PasswordSetupLink::NONE, $this->links()->stateOf($fresh));
        $this->assertTrue(Auth::validate(['email' => $user->email, 'password' => 'orange river keeps flowing north']));
        $this->assertSame(1, DB::table('password_securities')->where('user_id', $user->id)->count(), 'Their password clock starts now');
    }

    public function test_the_link_works_once(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();
        $this->setPassword($token, 'orange river keeps flowing north')->assertOk();

        $this->setPassword($token, 'a different long passphrase')->assertStatus(410)->assertJson(['link_problem' => 'used']);
        $this->assertTrue(Hash::check('orange river keeps flowing north', $user->fresh()->password), 'The second try changed nothing');
    }

    public function test_an_expired_link_cannot_set_a_password(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();
        $before = $user->fresh()->password;

        $this->travel(49)->hours();

        $this->setPassword($token, 'orange river keeps flowing north')->assertStatus(410)->assertJson(['link_problem' => 'expired']);
        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_a_refused_password_leaves_the_link_working(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();

        $this->setPassword($token, 'orange river keeps flowing north', 'something else entirely here')
            ->assertStatus(422)->assertJsonPath('message', 'The two passwords do not match.');
        $this->setPassword($token, 'too short')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'at least 12'));
        $this->setPassword($token, 'Password12345!')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'too common'));

        $this->assertSame(PasswordSetupLink::WAITING, $this->links()->stateOf($user->fresh()));
        $this->setPassword($token, 'orange river keeps flowing north')->assertOk();
    }

    public function test_setting_a_password_another_way_uses_the_link_up(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();

        // For example Forgot password, or the person changing it after someone else gave them access
        $user->password = 'chosen through forgot password flow';
        $user->save();

        $this->assertSame(PasswordSetupLink::NONE, $this->links()->stateOf($user->fresh()));
        $this->get(route('password.setup', ['token' => $token]))->assertStatus(410)->assertSee('already been used');
    }

    // ---- asking for a new link --------------------------------------------------------------------------------------------

    public function test_an_expired_link_can_ask_for_a_new_one_and_the_old_one_stays_dead(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $old = $this->lastToken();
        $this->travel(49)->hours();

        $this->post(route('password.setup.resend', ['token' => $old]))->assertOk()->assertSee('A new link has been sent');

        Mail::assertSent(SetPasswordMail::class, 2);
        $new = $this->lastToken();
        $this->assertNotSame($old, $new);
        $this->get(route('password.setup', ['token' => $old]))->assertStatus(404);
        $this->get(route('password.setup', ['token' => $new]))->assertOk();
    }

    public function test_asking_again_straight_away_does_not_send_another_email(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();

        $this->post(route('password.setup.resend', ['token' => $token]))->assertOk()->assertSee('moment ago');

        Mail::assertSent(SetPasswordMail::class, 1);
    }

    public function test_a_used_or_unknown_link_cannot_ask_for_a_new_one(): void
    {
        $user = $this->user();
        $this->links()->send($user);
        $token = $this->lastToken();
        $this->setPassword($token, 'orange river keeps flowing north')->assertOk();

        $this->post(route('password.setup.resend', ['token' => $token]))->assertStatus(410);
        $this->post(route('password.setup.resend', ['token' => str_repeat('b', 64)]))->assertStatus(404);

        Mail::assertSent(SetPasswordMail::class, 1);
    }

    // ---- the password rules -----------------------------------------------------------------------------------------------

    /** @dataProvider refusedPasswords */
    public function test_the_rules_refuse(string $password, string $reason): void
    {
        $user = $this->user(['name' => 'Amos Intutech', 'username' => 'amos.i', 'email' => 'amos.intutech@chabrinagencies.co.ke']);

        $problem = app(SetupPasswordRules::class)->problem($password, $user);

        $this->assertNotNull($problem, "'$password' should be refused ($reason)");
    }

    public static function refusedPasswords(): array
    {
        return [
            ['Short1!', 'too short'],
            ['elevenchars', 'one short of twelve'],
            ['Password12345!', 'common word with digits'],
            ['P@ssw0rd123456', 'common word with look-alikes'],
            ['Welcome@2026!!', 'common word with a year'],
            ['qwertyuiopas', 'keyboard run'],
            ['123456789012', 'digits in order'],
            ['aaaaaaaaaaaa', 'one character repeated'],
            ['passwordpassword', 'common word twice'],
            ['Amos-Intutech-2026!', 'contains the name'],
            ['my chabrin login 2026', 'contains the company name'],
            [str_repeat('x1', 70), 'longer than 128'],
        ];
    }

    /** @dataProvider acceptedPasswords */
    public function test_the_rules_accept(string $password): void
    {
        $user = $this->user(['name' => 'Amos Intutech', 'username' => 'amos.i', 'email' => 'amos.intutech@chabrinagencies.co.ke']);

        $this->assertNull(app(SetupPasswordRules::class)->problem($password, $user));
    }

    public static function acceptedPasswords(): array
    {
        return [
            ['correct horse battery staple'],
            ['orange river keeps flowing north'],
            ['tuesday coffee on the roof, again'],
            ['x9#Kq2!vLm8@Zr4$'],
        ];
    }

    public function test_the_password_security_settings_can_ask_for_more(): void
    {
        DB::table('cb_system_configs')->where('setting', 'password_security_enabled')->update(['value' => '1']);
        DB::table('cb_system_configs')->where('setting', 'password_min_length')->update(['value' => '16']);

        $rules = app(SetupPasswordRules::class);

        $this->assertSame(16, $rules->minLength());
        $this->assertStringContainsString('at least 16', (string) $rules->problem('brown fox jump', null), 'Fourteen characters is long enough for 12 but not for 16');
        // With the settings on, capitals and numbers are asked for too (that setting ships on)
        $this->assertNull($rules->problem('Brown Fox Jumps 42 Over', null));
    }

    public function test_complexity_is_only_asked_for_when_the_settings_ask_for_it(): void
    {
        $rules = app(SetupPasswordRules::class);
        $this->assertNull($rules->problem('all lowercase words here', null), 'A long phrase needs no capitals or numbers');

        DB::table('cb_system_configs')->where('setting', 'password_security_enabled')->update(['value' => '1']);
        DB::table('cb_system_configs')->where('setting', 'password_require_complexity')->update(['value' => '1']);

        $this->assertNotNull(app(SetupPasswordRules::class)->problem('all lowercase words here', null));
        $this->assertNull(app(SetupPasswordRules::class)->problem('Mixed Case words here 42', null));
    }

    // ---- the System Users page --------------------------------------------------------------------------------------------

    private function actAsAdmin(bool $allowed = true): void
    {
        $admin = \Mockery::mock(User::class)->makePartial();
        $admin->shouldReceive('hasPermission')->andReturn($allowed);
        $admin->shouldReceive('isAbleTo')->andReturn($allowed);
        $admin->id = 900;
        $this->actingAs($admin);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    public function test_adding_a_system_user_takes_no_password_and_emails_the_link(): void
    {
        $this->actAsAdmin();

        $this->postJson(route('user.ajax.store'), [
            'name' => 'Peter Otieno', 'username' => 'peter.o', 'email' => 'peter.o@chabrinagencies.co.ke', 'role_id' => 1,
            'password' => 'Admin123456!', // an old form still posting one must be ignored
        ])->assertOk()->assertJson(['success' => true, 'email_sent' => true]);

        $user = User::where('username', 'peter.o')->first();
        $this->assertFalse(Hash::check('Admin123456!', $user->password), 'The typed password is never used');
        $this->assertSame(PasswordSetupLink::WAITING, $this->links()->stateOf($user));
        Mail::assertSent(SetPasswordMail::class, fn (SetPasswordMail $mail) => $mail->hasTo('peter.o@chabrinagencies.co.ke'));
        $this->setPassword($this->lastToken(), 'orange river keeps flowing north')->assertOk();
        $this->assertTrue(Auth::validate(['email' => 'peter.o@chabrinagencies.co.ke', 'password' => 'orange river keeps flowing north']));
    }

    public function test_the_user_is_still_made_when_the_email_fails_and_the_page_says_so(): void
    {
        $this->actAsAdmin();
        Mail::swap(new class {
            public function to(...$arguments)
            {
                throw new \RuntimeException('smtp is down');
            }
        });

        $this->postJson(route('user.ajax.store'), [
            'name' => 'Peter Otieno', 'username' => 'peter.o', 'email' => 'peter.o@chabrinagencies.co.ke', 'role_id' => 1,
        ])->assertOk()->assertJson(['success' => true, 'email_sent' => false]);

        $this->assertNotNull(User::where('username', 'peter.o')->first());
    }

    public function test_the_list_shows_who_is_waiting_without_exposing_any_link(): void
    {
        $this->actAsAdmin();
        $waiting = $this->user(['username' => 'waiting.w', 'email' => 'waiting@chabrinagencies.co.ke', 'name' => 'Wanjiru Waiting']);
        $this->links()->send($waiting);
        $expired = $this->user(['username' => 'expired.e', 'email' => 'expired@chabrinagencies.co.ke', 'name' => 'Ekai Expired']);
        $this->links()->send($expired);
        User::whereKey($expired->id)->update(['password_setup_expires_at' => now()->subDay()]);
        $active = $this->user(['username' => 'active.a', 'email' => 'active@chabrinagencies.co.ke', 'name' => 'Achieng Active']);

        $response = $this->getJson(route('user.ajax.list'))->assertOk();

        $states = collect($response->json('users'))->pluck('setup_state', 'username');
        $this->assertSame('waiting', $states['waiting.w']);
        $this->assertSame('expired', $states['expired.e']);
        $this->assertSame('none', $states['active.a']);
        $this->assertStringNotContainsString('password_setup_token', $response->getContent());
        $this->assertStringNotContainsString(hash('sha256', $this->lastToken()), $response->getContent());
    }

    public function test_resend_link_replaces_the_link_for_someone_still_waiting(): void
    {
        $this->actAsAdmin();
        $user = $this->user();
        $this->links()->send($user);
        $old = $this->lastToken();

        $this->postJson(route('user.ajax.resend-link', $user->id))->assertOk()->assertJson(['success' => true]);

        Mail::assertSent(SetPasswordMail::class, 2);
        $this->assertNull($this->links()->find($old), 'The old link is cancelled');
        $this->assertNotNull($this->links()->find($this->lastToken()));
    }

    public function test_resend_link_never_returns_the_link_to_the_page(): void
    {
        $this->actAsAdmin();
        $user = $this->user();
        $this->links()->send($user);

        $body = $this->postJson(route('user.ajax.resend-link', $user->id))->assertOk()->getContent();

        $this->assertStringNotContainsString($this->lastToken(), $body);
        $this->assertStringNotContainsString('/set-password/', $body);
    }

    public function test_resend_link_is_refused_for_an_active_or_suspended_user_or_without_permission(): void
    {
        $this->actAsAdmin();
        $active = $this->user(['username' => 'active.a', 'email' => 'active@chabrinagencies.co.ke']);
        $this->postJson(route('user.ajax.resend-link', $active->id))->assertStatus(422);

        $suspended = $this->user(['username' => 'susp.s', 'email' => 'susp@chabrinagencies.co.ke', 'block' => 1]);
        $this->links()->issue($suspended);
        $this->postJson(route('user.ajax.resend-link', $suspended->id))->assertStatus(422);

        $waiting = $this->user(['username' => 'wait.w', 'email' => 'wait@chabrinagencies.co.ke']);
        $this->links()->issue($waiting);
        $this->actAsAdmin(false);
        $this->postJson(route('user.ajax.resend-link', $waiting->id))->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_correcting_the_email_of_someone_still_waiting_sends_the_new_link_there(): void
    {
        $this->actAsAdmin();
        $user = $this->user();
        $this->links()->send($user);
        $old = $this->lastToken();

        $this->postJson(route('user.ajax.update', $user->id), [
            'name' => $user->name, 'username' => $user->username, 'email' => 'lucy.corrected@chabrinagencies.co.ke', 'role_id' => 1,
        ])->assertOk();

        Mail::assertSent(SetPasswordMail::class, fn (SetPasswordMail $mail) => $mail->hasTo('lucy.corrected@chabrinagencies.co.ke'));
        $this->assertNull($this->links()->find($old));
    }

    public function test_editing_a_user_never_changes_their_password_or_sends_email(): void
    {
        $this->actAsAdmin();
        $user = $this->user();
        $this->links()->send($user);
        $before = $user->fresh()->password;

        $this->postJson(route('user.ajax.update', $user->id), [
            'name' => 'Lucy Wanjiru Karanja', 'username' => $user->username, 'email' => $user->email, 'role_id' => 1,
            'password' => 'Admin123456!',
        ])->assertOk();

        $this->assertSame($before, $user->fresh()->password);
        Mail::assertSent(SetPasswordMail::class, 1);
    }

    // ---- converting a staff member to a user ------------------------------------------------------------------------------

    public function test_converting_staff_to_a_user_emails_the_link_and_asks_for_no_password(): void
    {
        $this->actAsAdmin();
        $staffId = DB::table('sys_staff')->insertGetId(['full_names' => 'MARY WANJIKU', 'staff_number' => 'PER001']);

        $this->postJson(route('staff.convertToUser', Crypt::encrypt($staffId)), ['email' => 'mary.w@chabrinagencies.co.ke'])
            ->assertOk()->assertJson(['success' => true, 'email_sent' => true]);

        $user = User::where('email', 'mary.w@chabrinagencies.co.ke')->first();
        $this->assertNotNull($user);
        $this->assertSame(PasswordSetupLink::WAITING, $this->links()->stateOf($user));
        $this->assertSame(1, DB::table('sys_user_staff_links')->where('staff_id', $staffId)->count());
        Mail::assertSent(SetPasswordMail::class, fn (SetPasswordMail $mail) => $mail->hasTo('mary.w@chabrinagencies.co.ke'));
    }
}
