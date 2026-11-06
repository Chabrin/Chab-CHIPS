<?php

namespace Tests\Feature;

use App\Http\Controllers\RolesController;
use App\Providers\ViewServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The Human Resource menu entries and who may open them: the migration that creates them, the clean-up of double
 * access rows, the save-access action and the sidebar query.
 */
class MenuAccessTest extends TestCase
{
    private const HR_MODULE = 3;
    private const OTHER_MODULE = 8;

    private const DIRECTORS = 1;
    private const HR_MANAGER = 2;
    private const FIELD_OFFICER = 3;
    private const ACCOUNTANT = 4;
    private const SUPERVISOR_ROLE = 5;   // a role nobody is named for in the migration, held by a supervisor
    private const OTHER_ROLE = 6;

    private const MIGRATION = 'database/migrations/2026_11_05_200000_add_missing_hr_menu_entries_and_clean_duplicate_access.php';

    protected function setUp(): void
    {
        parent::setUp();

        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('cb_modules', function (Blueprint $t) {
            $t->id();
            $t->string('module_name');
        });
        Schema::create('cb_sub_modules', function (Blueprint $t) {
            $t->id();
            $t->string('sub_module_name')->nullable();
            $t->string('sub_module_url')->nullable();
            $t->string('icon')->nullable();
            $t->integer('order')->nullable();
            $t->string('added_by')->nullable();
            $t->dateTime('added_on')->nullable();
            $t->integer('module_id');
            $t->timestamps();
        });
        Schema::create('cb_sub_module_permissions', function (Blueprint $t) {
            $t->id();
            $t->integer('role_id');
            $t->integer('sub_module_id');
            $t->string('added_by')->nullable();
            $t->dateTime('added_on')->nullable();
            $t->timestamps();
        });
        Schema::create('cb_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
        });
        Schema::create('cb_role_user', function (Blueprint $t) {
            $t->integer('role_id');
            $t->integer('user_id');
            $t->string('user_type')->nullable();
        });
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('full_names')->nullable();
            $t->integer('position_id')->nullable();
            $t->integer('current_status_id')->default(1);
        });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) {
            $t->id();
            $t->string('position_name')->nullable();
            $t->integer('reports_to_id')->nullable();
        });
        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->rememberToken();
        });
        Schema::create('sys_user_staff_links', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->integer('staff_id');
        });

        DB::table('cb_modules')->insert([['id' => self::HR_MODULE, 'module_name' => 'Human Resource'], ['id' => self::OTHER_MODULE, 'module_name' => 'Other']]);
        DB::table('cb_roles')->insert([
            ['id' => self::DIRECTORS, 'name' => 'directors'], ['id' => self::HR_MANAGER, 'name' => 'hr_manager'],
            ['id' => self::FIELD_OFFICER, 'name' => 'field_officer'], ['id' => self::ACCOUNTANT, 'name' => 'accountant'],
            ['id' => self::SUPERVISOR_ROLE, 'name' => 'zone_supervisor'], ['id' => self::OTHER_ROLE, 'name' => 'other'],
        ]);
        DB::table('cb_sub_modules')->insert(['id' => 1, 'sub_module_name' => 'Employees', 'sub_module_url' => '/hr/employees', 'order' => 1, 'module_id' => self::HR_MODULE]);

        // A supervisor: someone holds a position that another position reports to, and has a login with a role
        DB::table('sys_set_staff_positions')->insert([['id' => 1, 'position_name' => 'Zone Manager', 'reports_to_id' => null], ['id' => 2, 'position_name' => 'Field Officer', 'reports_to_id' => 1]]);
        DB::table('sys_staff')->insert(['id' => 1, 'full_names' => 'Zoe Zone', 'position_id' => 1]);
        DB::table('josya_users')->insert(['id' => 100, 'name' => 'Zoe']);
        DB::table('sys_user_staff_links')->insert(['user_id' => 100, 'staff_id' => 1]);
        DB::table('cb_role_user')->insert(['role_id' => self::SUPERVISOR_ROLE, 'user_id' => 100]);
    }

    private function migrate(): void
    {
        (require base_path(self::MIGRATION))->up();
    }

    private function entry(string $url): ?object
    {
        return DB::table('cb_sub_modules')->where('sub_module_url', $url)->first();
    }

    private function rolesOf(int $entryId): array
    {
        return DB::table('cb_sub_module_permissions')->where('sub_module_id', $entryId)->pluck('role_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    private function grant(int $roleId, int $entryId, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            DB::table('cb_sub_module_permissions')->insert(['role_id' => $roleId, 'sub_module_id' => $entryId]);
        }
    }

    // ── the migration ─────────────────────────────────────────────────

    public function test_it_creates_each_missing_entry_once_for_the_roles_it_has_on_the_dev_server(): void
    {
        $this->migrate();

        foreach (['/hr/communication', '/hr/contracts/bulk-edit', '/discipline/tracker/appeals', '/hr/leave-management/pending-approvals', '/hr/training-materials'] as $url) {
            $this->assertSame(1, DB::table('cb_sub_modules')->where('sub_module_url', $url)->count(), $url);
            $this->assertSame(self::HR_MODULE, (int) $this->entry($url)->module_id);
        }

        $this->assertSame([self::DIRECTORS], $this->rolesOf($this->entry('/hr/communication')->id));
        $this->assertSame([self::DIRECTORS, self::HR_MANAGER, self::FIELD_OFFICER, self::ACCOUNTANT], $this->rolesOf($this->entry('/hr/training-materials')->id));
        $this->assertSame('Leave Pending Approvals', $this->entry('/hr/leave-management/pending-approvals')->sub_module_name);
        $this->assertSame('pending_actions', $this->entry('/hr/leave-management/pending-approvals')->icon);
    }

    public function test_leave_pending_approvals_also_goes_to_the_roles_of_supervisors(): void
    {
        $this->migrate();

        $this->assertSame(
            [self::DIRECTORS, self::HR_MANAGER, self::FIELD_OFFICER, self::SUPERVISOR_ROLE],
            $this->rolesOf($this->entry('/hr/leave-management/pending-approvals')->id)
        );
        // Only that page: the supervisor role gets nothing else
        $this->assertNotContains(self::SUPERVISOR_ROLE, $this->rolesOf($this->entry('/hr/training-materials')->id));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->migrate();
        $entries = DB::table('cb_sub_modules')->orderBy('id')->get()->toArray();
        $access = DB::table('cb_sub_module_permissions')->orderBy('id')->get()->toArray();

        $this->migrate();

        $this->assertEquals($entries, DB::table('cb_sub_modules')->orderBy('id')->get()->toArray());
        $this->assertEquals($access, DB::table('cb_sub_module_permissions')->orderBy('id')->get()->toArray());
    }

    public function test_an_entry_added_by_hand_with_a_slightly_wrong_url_is_corrected_not_copied(): void
    {
        $handMade = DB::table('cb_sub_modules')->insertGetId(['sub_module_name' => 'Leave Pending Approvals', 'sub_module_url' => 'HR/leave-management/pending-approvals/', 'order' => 20, 'module_id' => self::HR_MODULE]);
        $this->grant(self::ACCOUNTANT, $handMade);

        $this->migrate();

        $this->assertSame(1, DB::table('cb_sub_modules')->where('sub_module_name', 'Leave Pending Approvals')->count());
        $fixed = $this->entry('/hr/leave-management/pending-approvals');
        $this->assertSame($handMade, (int) $fixed->id);
        $this->assertSame(20, (int) $fixed->order);                       // its own place in the menu is kept
        $this->assertContains(self::ACCOUNTANT, $this->rolesOf($handMade)); // access someone gave by hand is kept
        $this->assertContains(self::HR_MANAGER, $this->rolesOf($handMade)); // and the missing roles are added
    }

    public function test_urls_typed_differently_are_recognised_as_the_same_page_whatever_the_entry_is_called(): void
    {
        $variants = ['/HR/Training-Materials/', 'hr/training-materials', 'https://chabrinagencies.casa/hr/training-materials?tab=1'];

        foreach ($variants as $i => $variant) {
            DB::table('cb_sub_modules')->delete();
            DB::table('cb_sub_module_permissions')->delete();
            DB::table('cb_sub_modules')->insert(['id' => 1, 'sub_module_name' => 'Employees', 'sub_module_url' => '/hr/employees', 'order' => 1, 'module_id' => self::HR_MODULE]);
            $handMade = DB::table('cb_sub_modules')->insertGetId(['sub_module_name' => 'Staff Learning ' . $i, 'sub_module_url' => $variant, 'order' => 30, 'module_id' => self::OTHER_MODULE]);
            $this->grant(self::OTHER_ROLE, $handMade);

            $this->migrate();

            $this->assertSame(1, DB::table('cb_sub_modules')->where('sub_module_url', '/hr/training-materials')->count(), $variant);
            $this->assertSame($handMade, (int) $this->entry('/hr/training-materials')->id, $variant);
            $this->assertSame('Staff Learning ' . $i, $this->entry('/hr/training-materials')->sub_module_name, $variant); // it keeps its own name
            $this->assertContains(self::OTHER_ROLE, $this->rolesOf($handMade), $variant);
        }
    }

    public function test_a_second_training_materials_entry_is_merged_into_the_working_one(): void
    {
        $working = DB::table('cb_sub_modules')->insertGetId(['sub_module_name' => 'Training Materials', 'sub_module_url' => '/hr/training-materials', 'order' => 17, 'module_id' => self::HR_MODULE]);
        $second = DB::table('cb_sub_modules')->insertGetId(['sub_module_name' => 'training materials ', 'sub_module_url' => '/hr/training', 'order' => 18, 'module_id' => self::HR_MODULE]);
        $this->grant(self::DIRECTORS, $working);
        $this->grant(self::OTHER_ROLE, $second); // someone was only given the copy

        $this->migrate();

        $this->assertSame(1, DB::table('cb_sub_modules')->whereRaw("LOWER(TRIM(sub_module_name)) = 'training materials'")->count());
        $this->assertNull(DB::table('cb_sub_modules')->find($second));
        $this->assertSame('/hr/training-materials', DB::table('cb_sub_modules')->find($working)->sub_module_url);
        $this->assertContains(self::OTHER_ROLE, $this->rolesOf($working)); // nobody loses the page
        $this->assertSame(0, DB::table('cb_sub_module_permissions')->where('sub_module_id', $second)->count());
    }

    public function test_the_same_name_in_another_module_is_left_alone(): void
    {
        $elsewhere = DB::table('cb_sub_modules')->insertGetId(['sub_module_name' => 'Training Materials', 'sub_module_url' => '/other/training', 'order' => 1, 'module_id' => self::OTHER_MODULE]);

        $this->migrate();

        $this->assertNotNull(DB::table('cb_sub_modules')->find($elsewhere));
        $this->assertSame('/other/training', DB::table('cb_sub_modules')->find($elsewhere)->sub_module_url);
    }

    public function test_double_access_rows_are_removed_keeping_the_first_and_leaving_other_roles_alone(): void
    {
        $this->grant(self::DIRECTORS, 1, 3);
        $this->grant(self::HR_MANAGER, 1);
        $this->grant(self::DIRECTORS, 1 + 100); // a different menu item for the same role
        $first = DB::table('cb_sub_module_permissions')->where('role_id', self::DIRECTORS)->where('sub_module_id', 1)->min('id');

        $this->migrate();

        $this->assertSame(1, DB::table('cb_sub_module_permissions')->where('role_id', self::DIRECTORS)->where('sub_module_id', 1)->count());
        $this->assertSame($first, (int) DB::table('cb_sub_module_permissions')->where('role_id', self::DIRECTORS)->where('sub_module_id', 1)->value('id'));
        $this->assertSame(1, DB::table('cb_sub_module_permissions')->where('role_id', self::HR_MANAGER)->where('sub_module_id', 1)->count());
        $this->assertSame(1, DB::table('cb_sub_module_permissions')->where('role_id', self::DIRECTORS)->where('sub_module_id', 101)->count());
    }

    public function test_without_a_human_resource_module_it_only_cleans_up_double_rows(): void
    {
        DB::table('cb_sub_modules')->delete();
        DB::table('cb_modules')->delete();
        $this->grant(self::DIRECTORS, 5, 2);

        $this->migrate();

        $this->assertSame(0, DB::table('cb_sub_modules')->count());
        $this->assertSame(1, DB::table('cb_sub_module_permissions')->count());
    }

    // ── saving a role's access ────────────────────────────────────────

    public function test_saving_a_roles_access_also_clears_its_double_rows(): void
    {
        DB::table('cb_sub_modules')->insert([['id' => 2, 'sub_module_name' => 'Training Materials', 'sub_module_url' => '/hr/training-materials', 'module_id' => self::HR_MODULE], ['id' => 3, 'sub_module_name' => 'Payslips', 'sub_module_url' => '/payroll/payslips', 'module_id' => self::HR_MODULE]]);
        $this->grant(self::DIRECTORS, 2, 2);
        $this->grant(self::DIRECTORS, 3);
        $this->grant(self::HR_MANAGER, 2, 2); // another role's doubles are not this save's business

        $response = app(RolesController::class)->saveModulesAccess(
            Request::create('/', 'POST', ['submodules' => [2, 3]]),
            Crypt::encrypt(self::DIRECTORS)
        );

        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame([2, 3], DB::table('cb_sub_module_permissions')->where('role_id', self::DIRECTORS)->orderBy('sub_module_id')->pluck('sub_module_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(2, DB::table('cb_sub_module_permissions')->where('role_id', self::HR_MANAGER)->count());
    }

    public function test_saving_a_roles_access_adds_ticked_items_and_removes_unticked_ones(): void
    {
        DB::table('cb_sub_modules')->insert([['id' => 2, 'sub_module_name' => 'A', 'sub_module_url' => '/a', 'module_id' => self::HR_MODULE], ['id' => 3, 'sub_module_name' => 'B', 'sub_module_url' => '/b', 'module_id' => self::HR_MODULE]]);
        $this->grant(self::HR_MANAGER, 2);

        app(RolesController::class)->saveModulesAccess(Request::create('/', 'POST', ['submodules' => [3]]), Crypt::encrypt(self::HR_MANAGER));

        $this->assertSame([3], DB::table('cb_sub_module_permissions')->where('role_id', self::HR_MANAGER)->pluck('sub_module_id')->map(fn ($id) => (int) $id)->all());
    }

    // ── the sidebar ───────────────────────────────────────────────────

    private function sidebarEntries(int $roleId)
    {
        $method = new ReflectionMethod(ViewServiceProvider::class, 'allowedSubmenus');
        $method->setAccessible(true);

        return $method->invoke(app()->getProvider(ViewServiceProvider::class), $roleId);
    }

    public function test_the_sidebar_shows_an_entry_once_even_when_the_role_was_granted_it_twice(): void
    {
        DB::table('cb_sub_modules')->insert([
            ['id' => 2, 'sub_module_name' => 'Training Materials', 'sub_module_url' => '/hr/training-materials', 'order' => 17, 'module_id' => self::HR_MODULE],
            ['id' => 3, 'sub_module_name' => 'Payslips', 'sub_module_url' => '/payroll/payslips', 'order' => 5, 'module_id' => self::HR_MODULE],
        ]);
        $this->grant(self::DIRECTORS, 2, 2);
        $this->grant(self::DIRECTORS, 3);
        $this->grant(self::HR_MANAGER, 1);

        $entries = $this->sidebarEntries(self::DIRECTORS);

        $this->assertSame(['Payslips', 'Training Materials'], $entries->pluck('sub_module_name')->all()); // in menu order, once each
        $this->assertSame([3, 2], $entries->pluck('id')->map(fn ($id) => (int) $id)->all());               // the entry's id, not the grant's
        $this->assertSame(['Employees'], $this->sidebarEntries(self::HR_MANAGER)->pluck('sub_module_name')->all());
        $this->assertCount(0, $this->sidebarEntries(self::OTHER_ROLE));
    }
}
