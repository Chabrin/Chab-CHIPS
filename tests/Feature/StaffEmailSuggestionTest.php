<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StaffEmailSuggestion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The official email suggested for a new staff member: firstname.surname@chabrinagencies.co.ke, with the middle initial added
 * (mary.a.wanjiku@...) and HR told when the plain address belongs to a current or former staff member already.
 */
class StaffEmailSuggestionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('full_names')->nullable();
            $t->string('email_address')->nullable();
            $t->integer('current_status_id')->default(1);
        });
        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            $t->string('email')->nullable();
        });
    }

    private function staff(string $names, string $email, int $status = 1): int
    {
        return DB::table('sys_staff')->insertGetId(['full_names' => $names, 'email_address' => $email, 'current_status_id' => $status]);
    }

    private function suggest(?string $names, ?int $except = null): array
    {
        return app(StaffEmailSuggestion::class)->suggest($names, $except);
    }

    // ── nothing in the way ────────────────────────────────────────────────

    public function test_a_name_nobody_has_gets_firstname_dot_surname_and_no_notice(): void
    {
        $this->assertSame(['email' => 'mary.wanjiku@chabrinagencies.co.ke', 'notice' => null], $this->suggest('Mary Wanjiku'));
    }

    public function test_the_middle_name_is_not_used_when_it_is_not_needed(): void
    {
        $this->assertSame('mary.wanjiku@chabrinagencies.co.ke', $this->suggest('Mary Ann Wanjiku')['email']);
    }

    public function test_one_name_gives_nothing_to_suggest(): void
    {
        $this->assertSame(['email' => null, 'notice' => null], $this->suggest('Mary'));
        $this->assertSame(['email' => null, 'notice' => null], $this->suggest(''));
        $this->assertSame(['email' => null, 'notice' => null], $this->suggest(null));
    }

    // ── the address is taken ──────────────────────────────────────────────

    public function test_a_second_mary_wanjiku_gets_the_middle_initial_and_hr_is_told(): void
    {
        $this->staff('MARY WANJIKU KAMAU', 'mary.wanjiku@chabrinagencies.co.ke');

        $result = $this->suggest('Mary Ann Wanjiku');

        $this->assertSame('mary.a.wanjiku@chabrinagencies.co.ke', $result['email']);
        $this->assertSame('mary.wanjiku@chabrinagencies.co.ke is already used by MARY WANJIKU KAMAU, so mary.a.wanjiku@chabrinagencies.co.ke was suggested. You can change it.', $result['notice']);
    }

    public function test_a_former_staff_members_address_counts_as_taken_and_the_notice_says_so(): void
    {
        $this->staff('MARY WANJIKU', 'mary.wanjiku@chabrinagencies.co.ke', 3);

        $result = $this->suggest('Mary Ann Wanjiku');

        $this->assertSame('mary.a.wanjiku@chabrinagencies.co.ke', $result['email']);
        $this->assertStringContainsString('is already used by MARY WANJIKU (former staff), so', $result['notice']);
    }

    public function test_suspended_and_on_leave_staff_are_current_not_former(): void
    {
        $this->staff('MARY WANJIKU', 'mary.wanjiku@chabrinagencies.co.ke', 2);

        $this->assertStringContainsString('is already used by MARY WANJIKU, so', $this->suggest('Mary Ann Wanjiku')['notice']);
    }

    public function test_the_match_ignores_capitals_in_the_stored_address(): void
    {
        $this->staff('MARY WANJIKU', 'Mary.Wanjiku@ChabrinAgencies.co.ke');

        $this->assertSame('mary.a.wanjiku@chabrinagencies.co.ke', $this->suggest('Mary Ann Wanjiku')['email']);
    }

    public function test_a_system_login_with_the_address_also_counts_even_when_no_staff_record_has_it(): void
    {
        DB::table('josya_users')->insert(['email' => 'mary.wanjiku@chabrinagencies.co.ke']);

        $result = $this->suggest('Mary Ann Wanjiku');

        $this->assertSame('mary.a.wanjiku@chabrinagencies.co.ke', $result['email']);
        $this->assertStringContainsString('is already used by a system user, so', $result['notice']);
    }

    public function test_when_the_middle_initial_address_is_taken_too_a_number_is_used(): void
    {
        $this->staff('MARY WANJIKU', 'mary.wanjiku@chabrinagencies.co.ke');
        $this->staff('MARY ANNE WANJIKU', 'mary.a.wanjiku@chabrinagencies.co.ke');

        $result = $this->suggest('Mary Ann Wanjiku');

        $this->assertSame('mary.wanjiku2@chabrinagencies.co.ke', $result['email']);
        $this->assertStringContainsString('mary.wanjiku2@chabrinagencies.co.ke was suggested', $result['notice']);
    }

    public function test_with_no_middle_name_a_number_tells_the_two_apart(): void
    {
        $this->staff('MARY WANJIKU', 'mary.wanjiku@chabrinagencies.co.ke');

        $this->assertSame('mary.wanjiku2@chabrinagencies.co.ke', $this->suggest('Mary Wanjiku')['email']);
    }

    public function test_numbers_are_skipped_until_one_is_free(): void
    {
        $this->staff('MARY WANJIKU', 'mary.wanjiku@chabrinagencies.co.ke');
        $this->staff('MARY W', 'mary.wanjiku2@chabrinagencies.co.ke');
        $this->staff('MARY WA', 'mary.wanjiku3@chabrinagencies.co.ke');

        $this->assertSame('mary.wanjiku4@chabrinagencies.co.ke', $this->suggest('Mary Wanjiku')['email']);
    }

    public function test_the_numbered_address_still_passes_the_format_check(): void
    {
        $this->staff('MARY WANJIKU', 'mary.wanjiku@chabrinagencies.co.ke');

        $this->assertTrue(\App\Support\StaffContact::isCompanyEmail($this->suggest('Mary Wanjiku')['email']));
    }

    public function test_a_staff_members_own_address_is_not_a_clash_when_they_are_the_one_being_edited(): void
    {
        $id = $this->staff('MARY WANJIKU', 'mary.wanjiku@chabrinagencies.co.ke');

        $this->assertSame(['email' => 'mary.wanjiku@chabrinagencies.co.ke', 'notice' => null], $this->suggest('Mary Wanjiku', $id));
        $this->assertSame('mary.wanjiku2@chabrinagencies.co.ke', $this->suggest('Mary Wanjiku', $id + 1)['email']);
    }

    public function test_another_persons_address_does_not_get_in_the_way(): void
    {
        $this->staff('JOHN KAMAU', 'john.kamau@chabrinagencies.co.ke');

        $this->assertSame('mary.wanjiku@chabrinagencies.co.ke', $this->suggest('Mary Wanjiku')['email']);
    }

    public function test_a_blank_owner_name_is_still_described(): void
    {
        $this->staff('', 'mary.wanjiku@chabrinagencies.co.ke');

        $this->assertStringContainsString('is already used by another staff member, so', $this->suggest('Mary Ann Wanjiku')['notice']);
    }

    // ── the endpoint ──────────────────────────────────────────────────────

    private function actingAsWho(string ...$permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    public function test_the_endpoint_returns_the_suggestion_and_notice_for_the_names_typed(): void
    {
        $this->actingAsWho('create_staff');
        $this->staff('MARY WANJIKU', 'mary.wanjiku@chabrinagencies.co.ke');

        $this->getJson(route('staff.suggest-email', ['full_names' => 'Mary Ann Wanjiku']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('email', 'mary.a.wanjiku@chabrinagencies.co.ke')
            ->assertJsonPath('notice', 'mary.wanjiku@chabrinagencies.co.ke is already used by MARY WANJIKU, so mary.a.wanjiku@chabrinagencies.co.ke was suggested. You can change it.');
    }

    public function test_the_endpoint_copes_with_nothing_typed_yet(): void
    {
        $this->actingAsWho('create_staff');

        $this->getJson(route('staff.suggest-email'))->assertOk()->assertJsonPath('email', null)->assertJsonPath('notice', null);
        $this->getJson(route('staff.suggest-email', ['full_names' => 'Mary']))->assertOk()->assertJsonPath('email', null);
    }

    public function test_the_endpoint_is_open_to_those_who_add_or_edit_staff_and_nobody_else(): void
    {
        $this->actingAsWho('update_staff');
        $this->getJson(route('staff.suggest-email', ['full_names' => 'Mary Wanjiku']))->assertOk();

        $this->actingAsWho('read_staff');
        $this->getJson(route('staff.suggest-email', ['full_names' => 'Mary Wanjiku']))->assertForbidden();
    }

    public function test_the_endpoint_refuses_overlong_names(): void
    {
        $this->actingAsWho('create_staff');

        $this->getJson(route('staff.suggest-email', ['full_names' => str_repeat('a', 300)]))->assertUnprocessable();
    }
}
