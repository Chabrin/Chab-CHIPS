<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * A person who worked here before is not added a second time: their record is brought back to Active, they keep their staff number,
 * and a new contract is added to it.
 */
class StaffRehireTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            foreach (['full_names', 'staff_number', 'national_id', 'mobile_number', 'hire_date'] as $column) {
                $t->string($column)->nullable();
            }
            foreach (['current_status_id', 'position_id'] as $column) {
                $t->integer($column)->nullable();
            }
        });
        DB::table('sys_staff')->insert([
            ['id' => 1, 'full_names' => 'MARY WANJIKU', 'staff_number' => 'PER017', 'national_id' => '12345678', 'mobile_number' => '+254700111222', 'current_status_id' => 3, 'position_id' => 4, 'hire_date' => '2019-03-01'],
            ['id' => 2, 'full_names' => 'JOHN OTIENO', 'staff_number' => 'PER018', 'national_id' => '87654321', 'mobile_number' => '+254700333444', 'current_status_id' => 1, 'position_id' => 5, 'hire_date' => '2020-01-01'],
        ]);
        $this->actAs(['read_staff', 'create_staff', 'update_staff', 'create_contracts']);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actAs(array $permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($p) => in_array($p, $permissions, true));
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    // ---- the check as an ID is typed --------------------------------------------------------------------------------------

    public function test_an_id_on_record_is_found_and_a_former_staff_member_can_be_rehired(): void
    {
        $response = $this->getJson(route('staff.former-match', ['national_id' => '12345678']))->assertOk()->assertJson([
            'found' => true, 'name' => 'MARY WANJIKU', 'staff_number' => 'PER017', 'status' => 'Terminated', 'can_rehire' => true,
        ]);

        $this->assertSame(1, $this->idIn($response->json('url')), 'the link opens their record');
    }

    /** The staff id inside an address like /hr/employees/{encrypted id} (the encryption is different every time, so it is opened). */
    private function idIn(string $url): int
    {
        return (int) Crypt::decrypt(basename(parse_url($url, PHP_URL_PATH)));
    }

    public function test_someone_still_working_here_is_found_but_cannot_be_rehired(): void
    {
        $this->getJson(route('staff.former-match', ['national_id' => '87654321']))->assertOk()->assertJson([
            'found' => true, 'status' => 'Active', 'can_rehire' => false,
        ]);
    }

    public function test_an_unknown_or_too_short_id_finds_nothing(): void
    {
        $this->getJson(route('staff.former-match', ['national_id' => '55555555']))->assertOk()->assertExactJson(['found' => false]);
        $this->getJson(route('staff.former-match', ['national_id' => '123']))->assertOk()->assertExactJson(['found' => false]);
        $this->getJson(route('staff.former-match'))->assertOk()->assertExactJson(['found' => false]);
    }

    public function test_the_answer_gives_nothing_else_away(): void
    {
        $body = $this->getJson(route('staff.former-match', ['national_id' => '12345678']))->getContent();

        $this->assertStringNotContainsString('254700111222', $body);
        $this->assertStringNotContainsString('12345678', $body);
        $this->assertSame(['can_rehire', 'found', 'name', 'staff_number', 'status', 'url'], collect(json_decode($body, true))->keys()->sort()->values()->all());
    }

    public function test_the_check_needs_permission(): void
    {
        $this->actAs(['read_contracts']);

        $this->getJson(route('staff.former-match', ['national_id' => '12345678']))->assertForbidden();
    }

    // ---- rehiring ---------------------------------------------------------------------------------------------------------

    public function test_rehiring_makes_them_active_on_the_same_record_with_the_same_staff_number(): void
    {
        $this->post(route('staff.rehire', Crypt::encrypt(1)), ['hire_date' => '2026-12-01'])
            ->assertRedirect(route('contract.create', ['staff_id' => 1, 'position_id' => 4]))
            ->assertSessionHas('success');

        $staff = Staff::find(1);
        $this->assertSame(1, (int) $staff->current_status_id);
        $this->assertSame('PER017', $staff->staff_number, 'they keep their staff number');
        $this->assertSame('2026-12-01', substr((string) $staff->hire_date, 0, 10));
        $this->assertSame(2, Staff::count(), 'nobody is added');
        $this->assertStringContainsString('PER017', session('success'));
    }

    public function test_without_a_date_today_is_the_new_hire_date(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-18 09:00:00'));

        $this->post(route('staff.rehire', Crypt::encrypt(1)))->assertRedirect();

        $this->assertSame('2026-11-18', substr((string) Staff::find(1)->hire_date, 0, 10));
    }

    public function test_someone_without_the_contract_permission_is_sent_to_the_profile_instead(): void
    {
        $this->actAs(['read_staff', 'update_staff']);

        $response = $this->post(route('staff.rehire', Crypt::encrypt(1)))->assertRedirect();

        $this->assertStringContainsString('/hr/employees/', $response->headers->get('Location'));
        $this->assertSame(1, $this->idIn($response->headers->get('Location')));
        $this->assertSame(1, (int) Staff::find(1)->current_status_id);
    }

    public function test_someone_who_has_not_left_cannot_be_rehired(): void
    {
        $response = $this->post(route('staff.rehire', Crypt::encrypt(2)), ['hire_date' => '2026-12-01'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(2, $this->idIn($response->headers->get('Location')));

        $this->assertSame('2020-01-01', substr((string) Staff::find(2)->hire_date, 0, 10), 'nothing changed');
    }

    public function test_rehiring_needs_the_update_permission_and_a_real_date(): void
    {
        $this->actAs(['read_staff', 'create_staff']);
        $this->post(route('staff.rehire', Crypt::encrypt(1)))->assertForbidden();
        $this->assertSame(3, (int) Staff::find(1)->current_status_id);

        $this->actAs(['update_staff']);
        $this->post(route('staff.rehire', Crypt::encrypt(1)), ['hire_date' => 'not a date'])->assertSessionHasErrors('hire_date');
        $this->assertSame(3, (int) Staff::find(1)->current_status_id);
    }

    // ---- the pages --------------------------------------------------------------------------------------------------------

    public function test_add_staff_checks_the_id_as_it_is_typed_and_loads_the_script(): void
    {
        $source = file_get_contents(resource_path('views/staff/create.blade.php'));

        $this->assertStringContainsString('data-former-url="{{ route(\'staff.former-match\') }}"', $source);
        $this->assertStringContainsString('id="formerMatch"', $source);
        $this->assertStringContainsString('assets/js/staff-former-match.js', $source);
    }

    public function test_the_profile_of_a_former_staff_member_has_a_rehire_button_only_for_those_who_left(): void
    {
        $source = file_get_contents(resource_path('views/staff/details.blade.php'));

        $this->assertStringContainsString('route(\'staff.rehire\'', $source);
        $this->assertStringContainsString('(int) $staff->current_status_id === 3', $source);
    }
}
