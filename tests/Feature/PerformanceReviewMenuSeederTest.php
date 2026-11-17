<?php

namespace Tests\Feature;

use Database\Seeders\PerformanceReviewMenuSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The menu seeder for HR > Performance Review: one module just below HR Reports, nine pages, and the proposed roles for each.
 * It must be safe to run twice, must only add, and must skip a role that does not exist.
 */
class PerformanceReviewMenuSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');

        Schema::create('cb_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
        });
        Schema::create('cb_modules', function (Blueprint $t) {
            $t->id();
            $t->string('module_name');
            $t->string('module_url')->nullable();
            $t->string('icon')->nullable();
            $t->integer('module_order')->nullable();
            $t->string('added_by')->nullable();
            $t->dateTime('added_on')->nullable();
            $t->timestamps();
            $t->integer('deleted')->default(0);
            $t->integer('super_module_id')->nullable();
        });
        Schema::create('cb_sub_modules', function (Blueprint $t) {
            $t->id();
            $t->string('sub_module_name');
            $t->string('sub_module_url')->nullable();
            $t->string('icon')->nullable();
            $t->integer('order')->nullable();
            $t->string('added_by')->nullable();
            $t->dateTime('added_on')->nullable();
            $t->integer('module_id')->nullable();
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

        // The HR group as it is today: Human Resource, HR Settings, Payroll Mgt, HR Reports, then Self Service far down; and another group
        foreach ([['Human Resource', 1, 3], ['HR Settings', 2, 3], ['Payroll Mgt', 3, 3], ['HR Reports', 4, 3], ['Self Service', 13, 3], ['Accounting', 5, 2]] as [$name, $order, $super]) {
            DB::table('cb_modules')->insert(['module_name' => $name, 'module_order' => $order, 'super_module_id' => $super]);
        }
        foreach (['directors', 'hr_manager', 'property_manager', 'zone_manager', 'senior_field_officer', 'field_officer', 'accountant', 'assistant_accountant', 'internal_auditor', 'qc_manager', 'it_department', 'office_admin_payments', 'office_assistant'] as $role) {
            DB::table('cb_roles')->insert(['name' => $role]);
        }
    }

    private function runSeeder(): void
    {
        (new PerformanceReviewMenuSeeder)->run();
    }

    private function rolesOf(string $url): array
    {
        return DB::table('cb_sub_module_permissions as p')->join('cb_roles as r', 'r.id', '=', 'p.role_id')->join('cb_sub_modules as s', 's.id', '=', 'p.sub_module_id')
            ->where('s.sub_module_url', $url)->orderBy('r.name')->pluck('r.name')->all();
    }

    public function test_the_module_goes_into_the_hr_group_just_below_hr_reports(): void
    {
        $this->runSeeder();

        $module = DB::table('cb_modules')->where('module_name', 'Performance Review')->first();
        $this->assertSame(3, (int) $module->super_module_id);
        $this->assertSame(5, (int) $module->module_order, 'HR Reports is 4 and Self Service 13');
        $this->assertSame('rate_review', $module->icon);

        $hrOrder = DB::table('cb_modules')->where('super_module_id', 3)->orderBy('module_order')->pluck('module_name')->all();
        $this->assertSame(['Human Resource', 'HR Settings', 'Payroll Mgt', 'HR Reports', 'Performance Review', 'Self Service'], $hrOrder);
    }

    public function test_the_nine_pages_are_added_in_order_under_the_module(): void
    {
        $this->runSeeder();
        $moduleId = DB::table('cb_modules')->where('module_name', 'Performance Review')->value('id');

        $pages = DB::table('cb_sub_modules')->where('module_id', $moduleId)->orderBy('order')->get(['sub_module_name', 'sub_module_url'])->map(fn ($p) => [$p->sub_module_name, $p->sub_module_url])->all();

        $this->assertSame([
            ['My Review', '/hr/performance/my-review'],
            ['Team Reviews', '/hr/performance/team-reviews'],
            ['All Reviews', '/hr/performance/all-reviews'],
            ['Review Cycles', '/hr/performance/cycles'],
            ['KPIs', '/hr/performance/kpis'],
            ['Supervisors', '/hr/performance/supervisors'],
            ['Meetings', '/hr/performance/meetings'],
            ['Appeals', '/hr/performance/appeals'],
            ['Committee', '/hr/performance/committee'],
        ], $pages);
    }

    public function test_the_page_urls_are_the_real_routes(): void
    {
        $this->runSeeder();
        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => '/'.ltrim($route->uri(), '/'))->all();

        foreach (DB::table('cb_sub_modules')->pluck('sub_module_url') as $url) {
            $this->assertContains($url, $routes, $url);
        }
    }

    public function test_only_hr_roles_see_the_set_up_pages_and_supervisors_see_team_reviews(): void
    {
        $this->runSeeder();

        foreach (['/hr/performance/all-reviews', '/hr/performance/cycles', '/hr/performance/kpis', '/hr/performance/supervisors', '/hr/performance/appeals', '/hr/performance/committee'] as $url) {
            $this->assertSame(['directors', 'hr_manager'], $this->rolesOf($url), $url);
        }
        $this->assertCount(13, $this->rolesOf('/hr/performance/meetings'), 'a committee member of any role opens the meetings they sit on');
        $team = $this->rolesOf('/hr/performance/team-reviews');
        foreach (['directors', 'hr_manager', 'property_manager', 'zone_manager', 'senior_field_officer', 'accountant'] as $supervisor) {
            $this->assertContains($supervisor, $team);
        }
        foreach (['field_officer', 'assistant_accountant', 'it_department'] as $notSupervisor) {
            $this->assertNotContains($notSupervisor, $team, 'a position nobody reports to has no team');
        }
        $this->assertCount(13, $this->rolesOf('/hr/performance/my-review'), 'every role that has staff with a login');
    }

    public function test_a_role_that_does_not_exist_is_skipped_not_created(): void
    {
        $this->runSeeder();

        $this->assertSame(0, DB::table('cb_roles')->where('name', 'superadministrator')->count());
        $this->assertSame(13, DB::table('cb_roles')->count());
    }

    public function test_running_it_again_adds_nothing(): void
    {
        $this->runSeeder();
        $counts = [DB::table('cb_modules')->count(), DB::table('cb_sub_modules')->count(), DB::table('cb_sub_module_permissions')->count()];

        $this->runSeeder();

        $this->assertSame($counts, [DB::table('cb_modules')->count(), DB::table('cb_sub_modules')->count(), DB::table('cb_sub_module_permissions')->count()]);
    }

    public function test_it_only_adds_and_leaves_changes_made_by_hand_alone(): void
    {
        $this->runSeeder();
        $kpis = DB::table('cb_sub_modules')->where('sub_module_url', '/hr/performance/kpis')->value('id');
        DB::table('cb_sub_module_permissions')->where('sub_module_id', $kpis)->delete();      // someone took the page away from every role
        DB::table('cb_sub_modules')->where('sub_module_url', '/hr/performance/cycles')->update(['sub_module_name' => 'Cycles (renamed)']);

        $this->runSeeder();

        $this->assertSame('Cycles (renamed)', DB::table('cb_sub_modules')->where('sub_module_url', '/hr/performance/cycles')->value('sub_module_name'), 'a name changed by hand stays');
        $this->assertSame(['directors', 'hr_manager'], $this->rolesOf('/hr/performance/kpis'), 'the seeder puts back only what its own run would add');
    }

    public function test_the_other_groups_and_existing_modules_are_untouched(): void
    {
        $before = DB::table('cb_modules')->orderBy('id')->get()->all();

        $this->runSeeder();

        $after = DB::table('cb_modules')->whereIn('module_name', ['Human Resource', 'HR Settings', 'Payroll Mgt', 'HR Reports', 'Self Service', 'Accounting'])->orderBy('id')->get()->all();
        $this->assertEquals($before, $after);
    }

    public function test_the_seeder_is_not_part_of_the_default_seeding(): void
    {
        $default = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('PerformanceReviewMenuSeeder', $default);
    }
}
