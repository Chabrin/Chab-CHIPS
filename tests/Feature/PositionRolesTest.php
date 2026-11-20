<?php

namespace Tests\Feature;

use App\Http\Controllers\HrSettingsController;
use App\Http\Controllers\StaffPositionRoleController;
use App\Models\Position;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\PositionRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * Every position has one system role, made when the position is created, so the Position and Role lists match. A role made this way
 * has no access. Existing positions are linked with the plan/apply pair (hr:link-position-roles), and nobody's access changes.
 */
class PositionRolesTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        Model::unsetEventDispatcher(); // the audit-log observers need their own tables
        Schema::create('cb_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('display_name')->nullable();
            $t->string('description')->nullable();
            $t->boolean('is_global')->default(false);
            $t->timestamps();
        });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) {
            $t->id();
            $t->integer('department_id')->nullable();
            $t->string('position_name');
            $t->string('slug_name')->nullable();
            $t->decimal('basic_salary', 12, 2)->nullable();
            $t->integer('salary_account_id')->nullable();
            $t->integer('current_status_id')->nullable();
            $t->integer('reports_to_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('role_id')->nullable();
            $t->dateTime('date_time')->nullable();
        });
        Schema::create('sys_set_departments', function (Blueprint $t) { $t->id(); $t->string('department_name')->nullable(); });
        Schema::create('sys_staff', function (Blueprint $t) { $t->id(); $t->string('full_names')->nullable(); $t->integer('position_id')->nullable(); });
        Schema::create('sys_user_staff_links', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('staff_id'); });
        DB::table('sys_set_departments')->insert(['id' => 1, 'department_name' => 'Operations']);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function position(string $name, ?string $slug = null, ?int $roleId = null): Position
    {
        return Position::create(['position_name' => $name, 'slug_name' => $slug ?? \Illuminate\Support\Str::slug($name, '_'), 'role_id' => $roleId, 'department_id' => 1]);
    }

    private function role(string $name, string $display): Role
    {
        $role = new Role();
        $role->name = $name;
        $role->display_name = $display;
        $role->save();

        return $role;
    }

    private function roleUsers(int $roleId, int ...$userIds): void
    {
        foreach ($userIds as $userId) {
            DB::table('cb_role_user')->insert(['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    // ---- a new position gets its role ---------------------------------------------------------------------------------------

    public function test_a_new_position_gets_a_role_named_after_it_with_no_access(): void
    {
        $position = $this->position('Audit Assistant');

        $role = app(PositionRoles::class)->createFor($position);

        $this->assertSame('audit_assistant', $role->name);
        $this->assertSame('Audit Assistant', $role->display_name);
        $this->assertSame($role->id, (int) $position->fresh()->role_id);
        $this->assertFalse((bool) $role->fresh()->is_global, 'it sees only what it is assigned');
        $this->assertSame(0, DB::table('cb_sub_module_permissions')->where('role_id', $role->id)->count(), 'no menus until an administrator grants them');
        $this->assertStringContainsString('no access', $role->description);
    }

    public function test_a_second_call_gives_the_same_role_and_never_a_duplicate(): void
    {
        $position = $this->position('Audit Assistant');
        $service = app(PositionRoles::class);

        $first = $service->createFor($position);
        $second = $service->createFor($position->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Role::count());
    }

    public function test_a_role_key_that_is_taken_gets_a_number(): void
    {
        $this->role('audit_assistant', 'Something else');
        $position = $this->position('Audit Assistant');

        $role = app(PositionRoles::class)->createFor($position);

        $this->assertSame('audit_assistant_2', $role->name);
        $this->assertSame(2, Role::count());
    }

    public function test_a_renamed_position_keeps_its_role_under_the_new_name_and_the_key_stays(): void
    {
        $position = $this->position('Audit Assistant');
        $role = app(PositionRoles::class)->createFor($position);

        $position->position_name = 'Audit Officer';
        $position->save();
        app(PositionRoles::class)->syncName($position);

        $fresh = $role->fresh();
        $this->assertSame('Audit Officer', $fresh->display_name);
        $this->assertSame('audit_assistant', $fresh->name, 'permissions and settings that refer to the key keep working');
    }

    public function test_creating_a_position_in_hr_settings_makes_its_role_and_renaming_it_renames_the_role(): void
    {
        $controller = app(HrSettingsController::class);

        $response = $controller->positionsStoreAjax(Request::create('/', 'POST', ['department_id' => 1, 'position_name' => 'Records Clerk']));
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('role was made', $data['message']);
        $position = Position::where('position_name', 'Records Clerk')->firstOrFail();
        $this->assertNotNull($position->role_id);
        $this->assertSame('Records Clerk', Role::find($position->role_id)->display_name);

        $controller->positionsUpdateAjax(Request::create('/', 'POST', ['department_id' => 1, 'position_name' => 'Records Officer']), $position->id);

        $this->assertSame('Records Officer', Role::find($position->role_id)->display_name);
        $this->assertSame('records_clerk', Role::find($position->role_id)->name);
    }

    // ---- linking the positions that already exist ---------------------------------------------------------------------------

    public function test_the_plan_uses_an_existing_role_by_the_configured_pair_by_key_or_by_name_and_makes_one_otherwise(): void
    {
        config(['position_roles.existing' => ['operations_hr_manager' => 'hr_manager']]);
        $hr = $this->role('hr_manager', 'HR Manager');
        $accountant = $this->role('accountant', 'Accountant');
        $auditor = $this->role('auditing', 'Internal Auditor');
        $this->position('Operations & HR Manager', 'operations_hr_manager');
        $this->position('Accountant');
        $this->position('Internal Auditor');
        $this->position('Audit Assistant');

        $plan = app(PositionRoles::class)->plan()->keyBy(fn ($item) => $item['position']->position_name);

        $this->assertSame([$hr->id, 'use_existing'], [$plan['Operations & HR Manager']['role']->id, $plan['Operations & HR Manager']['action']], 'by the configured pair');
        $this->assertSame($accountant->id, $plan['Accountant']['role']->id, 'by the key');
        $this->assertSame($auditor->id, $plan['Internal Auditor']['role']->id, 'by the display name');
        $this->assertSame(['create', null], [$plan['Audit Assistant']['action'], $plan['Audit Assistant']['role']]);
    }

    public function test_the_plan_counts_staff_and_the_people_who_hold_the_role_and_skips_linked_positions(): void
    {
        $hr = $this->role('hr_manager', 'HR Manager');
        $this->roleUsers($hr->id, 1, 2, 3);
        $position = $this->position('HR Manager');
        DB::table('sys_staff')->insert([['full_names' => 'A', 'position_id' => $position->id], ['full_names' => 'B', 'position_id' => $position->id]]);
        $this->position('Already Linked', null, $hr->id);

        $plan = app(PositionRoles::class)->plan();

        $this->assertCount(1, $plan, 'a position that has its role is not in the plan');
        $this->assertSame([2, 3], [$plan[0]['staff'], $plan[0]['role_users']]);
    }

    public function test_applying_links_and_creates_without_changing_anyones_access(): void
    {
        $accountant = $this->role('accountant', 'Accountant');
        $this->roleUsers($accountant->id, 10, 11, 12);
        $this->position('Chief Accountant', 'chief_accountant');
        config(['position_roles.existing' => ['chief_accountant' => 'accountant']]);
        $this->position('Audit Assistant');

        $done = app(PositionRoles::class)->apply();

        $this->assertSame(['linked' => 1, 'created' => 1], $done);
        $chief = Position::where('position_name', 'Chief Accountant')->first();
        $this->assertSame($accountant->id, (int) $chief->role_id);
        $this->assertSame('Chief Accountant', $accountant->fresh()->display_name, 'the existing role shows under the position name');
        $this->assertSame('accountant', $accountant->fresh()->name, 'its key is unchanged');
        $this->assertNotNull(Position::where('position_name', 'Audit Assistant')->first()->role_id);
        $this->assertSame([10, 11, 12], DB::table('cb_role_user')->where('role_id', $accountant->id)->pluck('user_id')->sort()->values()->all(), 'nobody lost or gained a role');
        $this->assertSame(0, app(PositionRoles::class)->plan()->count(), 'a second run has nothing to do');
    }

    public function test_a_role_that_two_positions_share_keeps_its_own_name(): void
    {
        // The Managing Director and Board Members both have the Directors role: it must not end up called after one of them
        $directors = $this->role('directors', 'Directors');
        config(['position_roles.existing' => ['managing_director' => 'directors', 'board_members' => 'directors']]);
        $this->position('Board Members', 'board_members');
        $this->position('MANAGING DIRECTOR', 'managing_director');

        $done = app(PositionRoles::class)->apply();

        $this->assertSame(['linked' => 2, 'created' => 0], $done);
        $this->assertSame('Directors', $directors->fresh()->display_name);
        $this->assertSame([$directors->id, $directors->id], Position::orderBy('id')->pluck('role_id')->map(fn ($v) => (int) $v)->all());

        // Renaming one of them later does not rename the shared role either
        $position = Position::where('position_name', 'Board Members')->first();
        $position->position_name = 'The Board';
        $position->save();
        app(PositionRoles::class)->syncName($position);
        $this->assertSame('Directors', $directors->fresh()->display_name);
    }

    public function test_the_command_only_shows_the_plan_until_it_is_told_to_apply(): void
    {
        $this->position('Audit Assistant');

        $this->artisan('hr:link-position-roles')
            ->expectsOutputToContain('Audit Assistant')
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();
        $this->assertNull(Position::first()->role_id);
        $this->assertSame(0, Role::count());

        $this->artisan('hr:link-position-roles --apply')->expectsOutputToContain('1 new roles created')->assertSuccessful();

        $this->assertNotNull(Position::first()->role_id);
        $this->assertSame(1, Role::count());
        $this->artisan('hr:link-position-roles')->expectsOutput('Every position already has a role.')->assertSuccessful();
    }

    // ---- a staff member's position changes ----------------------------------------------------------------------------------

    private function staffWithLogin(int $positionId, int $roleId): Staff
    {
        $staffId = DB::table('sys_staff')->insertGetId(['full_names' => 'MARY WANJIKU', 'position_id' => $positionId]);
        DB::table('sys_user_staff_links')->insert(['user_id' => 50, 'staff_id' => $staffId]);
        DB::table('cb_role_user')->insert(['user_id' => 50, 'role_id' => $roleId]);

        return Staff::find($staffId);
    }

    public function test_a_position_change_offers_the_new_roles_for_the_login_and_changes_nothing_by_itself(): void
    {
        $old = $this->role('accountant', 'Accountant');
        $new = $this->role('senior_accountant', 'Senior Accountant');
        $oldPosition = $this->position('Accountant', null, $old->id);
        $newPosition = $this->position('Senior Accountant', null, $new->id);
        $staff = $this->staffWithLogin($oldPosition->id, $old->id);
        $staff->position_id = $newPosition->id;
        $staff->save();

        $offer = StaffPositionRoleController::offerFor($staff->fresh(), $oldPosition->id);

        $this->assertSame(['MARY WANJIKU', 'Senior Accountant'], [$offer['name'], $offer['role_name']]);
        $segments = explode('/', trim(parse_url($offer['url'], PHP_URL_PATH), '/'));
        $this->assertSame('apply-position-role', end($segments));
        $this->assertSame($staff->id, (int) Crypt::decrypt($segments[count($segments) - 2]), 'the address is for this staff member');
        $this->assertSame($old->id, (int) DB::table('cb_role_user')->where('user_id', 50)->value('role_id'), 'the offer alone changes nothing');
    }

    public function test_no_offer_when_the_position_is_the_same_the_role_is_held_already_or_there_is_no_login(): void
    {
        $role = $this->role('accountant', 'Accountant');
        $position = $this->position('Accountant', null, $role->id);
        $staff = $this->staffWithLogin($position->id, $role->id);

        $this->assertNull(StaffPositionRoleController::offerFor($staff, $position->id), 'same position');

        $other = $this->position('Cashier', null, $role->id);
        $staff->position_id = $other->id;
        $staff->save();
        $this->assertNull(StaffPositionRoleController::offerFor($staff->fresh(), $position->id), 'their login already holds that role');

        $loose = $this->position('Clerk', null, $this->role('clerk', 'Clerk')->id);
        $noLogin = Staff::find(DB::table('sys_staff')->insertGetId(['full_names' => 'NO LOGIN', 'position_id' => $loose->id]));
        $this->assertNull(StaffPositionRoleController::offerFor($noLogin, $position->id), 'no login to change');

        $noRole = $this->position('Driver');
        $staff->position_id = $noRole->id;
        $staff->save();
        $this->assertNull(StaffPositionRoleController::offerFor($staff->fresh(), $position->id), 'the new position has no role yet');
    }

    public function test_saying_yes_gives_the_login_the_role_of_the_position_and_it_needs_permission(): void
    {
        $old = $this->role('accountant', 'Accountant');
        $new = $this->role('senior_accountant', 'Senior Accountant');
        $oldPosition = $this->position('Accountant', null, $old->id);
        $newPosition = $this->position('Senior Accountant', null, $new->id);
        $staff = $this->staffWithLogin($oldPosition->id, $old->id);
        $staff->position_id = $newPosition->id;
        $staff->save();

        $this->actAs(['read_staff']);
        $this->post(route('staff.apply-position-role', Crypt::encrypt($staff->id)))->assertForbidden();
        $this->assertSame($old->id, (int) DB::table('cb_role_user')->where('user_id', 50)->value('role_id'));

        $this->actAs(['update_users']);
        $this->post(route('staff.apply-position-role', Crypt::encrypt($staff->id)))->assertRedirect()->assertSessionHas('success');

        $this->assertSame([$new->id], DB::table('cb_role_user')->where('user_id', 50)->pluck('role_id')->map(fn ($v) => (int) $v)->all(), 'one role, the new one');
    }

    private function actAs(array $permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($p) => in_array($p, $permissions, true));
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    // ---- the form ---------------------------------------------------------------------------------------------------------

    public function test_add_staff_has_no_role_box_the_role_is_shown_from_the_position(): void
    {
        $source = file_get_contents(resource_path('views/staff/create.blade.php'));

        $this->assertStringContainsString('data-role-id="{{ $position->role_id }}"', $source);
        $this->assertStringContainsString('data-role-name="{{ $position->role?->display_name }}"', $source);
        $this->assertStringContainsString('id="role_display"', $source);
        $this->assertStringContainsString('id="roleNote"', $source);
        $this->assertStringContainsString('assets/js/position-role.js', $source);
        $this->assertStringNotContainsString('name="role_id"', $source, 'there is no role box: the server takes the role from the position');
        // When the department changes the position list is rebuilt: every option keeps its role, and the role box is refreshed
        $this->assertStringContainsString(".attr('data-role-id', \$opt.attr('data-role-id')", $source);
        $this->assertStringContainsString('window.PositionRole.refresh()', $source);
    }

    // ---- the role Add Staff gives a position ------------------------------------------------------------------------------

    public function test_role_for_gives_the_linked_role_matches_an_existing_one_or_makes_a_new_one(): void
    {
        $accountant = $this->role('accountant', 'Accountant');
        $service = app(PositionRoles::class);

        $linked = $this->position('Cashier', null, $accountant->id);
        $this->assertSame($accountant->id, $service->roleFor($linked)->id, 'the role the position was made with');

        $matched = $this->position('Accountant');
        $this->assertSame($accountant->id, $service->roleFor($matched)->id, 'a position that is not linked yet finds the role with its key');
        $this->assertSame($accountant->id, (int) $matched->fresh()->role_id, 'and is linked for next time');
        $this->assertSame(1, Role::count(), 'no duplicate');

        $new = $this->position('Records Clerk');
        $role = $service->roleFor($new);
        $this->assertSame('Records Clerk', $role->display_name);
        $this->assertSame($role->id, (int) $new->fresh()->role_id);
        $this->assertSame(0, DB::table('cb_sub_module_permissions')->where('role_id', $role->id)->count(), 'a new role has no access');
    }
}
