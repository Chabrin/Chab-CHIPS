<?php

namespace Tests\Feature;

use App\Http\Middleware\AccessChecker;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The SHA Rates page lives at /hrsettings/sha/rates. The routes, the page's own calls and the menu row that decides
 * who may open it all have to agree on that address.
 */
class ShaRatesPathTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const OLD_PAGE = '/hrsettings/shif/rates';
    private const NEW_PAGE = '/hrsettings/sha/rates';

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        // The plain layout's menu tables are empty and bare; give the menu table the columns this test needs
        Schema::table('cb_sub_modules', function (Blueprint $table) {
            $table->string('sub_module_name')->nullable();
            $table->string('sub_module_url')->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function migration()
    {
        return require database_path('migrations/2026_11_09_100000_move_sha_rates_menu_to_sha_path.php');
    }

    private function menuRow(string $name, string $url): int
    {
        return DB::table('cb_sub_modules')->insertGetId(['sub_module_name' => $name, 'sub_module_url' => $url, 'module_id' => 5, 'order' => 6]);
    }

    private function urlOf(int $id): ?string
    {
        return DB::table('cb_sub_modules')->where('id', $id)->value('sub_module_url');
    }

    // ── the routes ───────────────────────────────────────────────────────

    public function test_the_sha_rates_page_and_its_calls_are_on_the_sha_path(): void
    {
        $this->assertSame(self::NEW_PAGE, route('settings.hr.shif.rates', [], false));
        $this->assertSame('/ajax/hr/sha/rates/list', route('ajax.shifRates.list', [], false));
        $this->assertSame('/ajax/hr/sha/rates', route('ajax.shifRates.store', [], false));
        $this->assertSame('/ajax/hr/sha/rates/7', route('ajax.shifRates.update', 7, false));
        $this->assertSame('/ajax/hr/sha/rates/7', route('ajax.shifRates.delete', 7, false));
    }

    public function test_the_old_path_is_gone(): void
    {
        $this->get(self::OLD_PAGE)->assertNotFound();
        $this->getJson('/ajax/hr/shif/rates/list')->assertNotFound();
    }

    private function matched(string $method, string $uri): ?LaravelRoute
    {
        try {
            return Route::getRoutes()->match(Request::create($uri, $method));
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException|\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            return null;
        }
    }

    public function test_every_address_the_page_calls_reaches_a_route(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturn(true);
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->id = 7;
        $this->actingAs($user);

        $page = view('settings.hr.shif_rates')->render();

        $this->assertStringNotContainsString('/shif/', $page);
        $this->assertStringContainsString('/ajax/hr/sha/rates/list', $page);        // loading the table
        $this->assertStringContainsString('/ajax/hr/sha/rates"', $page);            // saving a new rate
        $this->assertStringContainsString('/ajax/hr/sha/rates/" + id', $page);      // saving an edit
        $this->assertStringContainsString("/ajax/hr/sha/rates/\" + \$btn.data('id')", $page); // deleting
        $this->assertNotNull($this->matched('GET', '/ajax/hr/sha/rates/list'));
        $this->assertNotNull($this->matched('POST', '/ajax/hr/sha/rates'));
        $this->assertNotNull($this->matched('POST', '/ajax/hr/sha/rates/5'));
        $this->assertNotNull($this->matched('DELETE', '/ajax/hr/sha/rates/5'));
        $this->assertNotNull($this->matched('GET', self::NEW_PAGE));
    }

    // ── the menu row ────────────────────────────────────────────────────

    public function test_the_migration_moves_the_menu_row_to_the_new_path(): void
    {
        $id = $this->menuRow('SHA Rates', self::OLD_PAGE);

        $this->migration()->up();

        $this->assertSame(self::NEW_PAGE, $this->urlOf($id));
        $row = DB::table('cb_sub_modules')->find($id);
        $this->assertEquals(['SHA Rates', 5, 6], [$row->sub_module_name, $row->module_id, $row->order]);
        $this->assertSame(1, DB::table('cb_sub_modules')->count());
    }

    public function test_the_migration_leaves_other_menu_rows_alone(): void
    {
        $nssf = $this->menuRow('NSSF Rates', '/hrsettings/nssf/rates');
        $other = $this->menuRow('Leave', '/hr/leave');
        $this->menuRow('SHA Rates', self::OLD_PAGE);

        $this->migration()->up();

        $this->assertSame('/hrsettings/nssf/rates', $this->urlOf($nssf));
        $this->assertSame('/hr/leave', $this->urlOf($other));
    }

    public function test_running_the_migration_twice_changes_nothing_more(): void
    {
        $id = $this->menuRow('SHA Rates', self::OLD_PAGE);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(self::NEW_PAGE, $this->urlOf($id));
        $this->assertSame(1, DB::table('cb_sub_modules')->count());
    }

    public function test_a_row_that_already_has_the_new_path_is_not_duplicated(): void
    {
        $old = $this->menuRow('SHA Rates', self::OLD_PAGE);
        $new = $this->menuRow('SHA Rates', self::NEW_PAGE);

        $this->migration()->up();

        $this->assertSame(self::OLD_PAGE, $this->urlOf($old), 'two rows on one address would leave the access check guessing');
        $this->assertSame(self::NEW_PAGE, $this->urlOf($new));
    }

    public function test_the_migration_can_be_undone(): void
    {
        $id = $this->menuRow('SHA Rates', self::OLD_PAGE);
        $this->migration()->up();

        $this->migration()->down();

        $this->assertSame(self::OLD_PAGE, $this->urlOf($id));
    }

    public function test_the_migration_is_harmless_without_a_menu_table(): void
    {
        Schema::drop('cb_sub_modules');

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('cb_sub_modules'));
    }

    // ── who may open it ────────────────────────────────────────────────

    private function openPageAs(int $roleId): \Symfony\Component\HttpFoundation\Response
    {
        $user = new User();
        $user->id = 7;
        $this->actingAs($user);
        DB::table('cb_role_user')->where('user_id', 7)->delete();
        DB::table('cb_role_user')->insert(['user_id' => 7, 'role_id' => $roleId]);

        return (new AccessChecker())->handle(Request::create(self::NEW_PAGE), fn () => response('the page'));
    }

    public function test_after_the_migration_only_roles_with_access_can_open_the_new_path(): void
    {
        $id = $this->menuRow('SHA Rates', self::OLD_PAGE);
        DB::table('cb_sub_module_permissions')->insert(['sub_module_id' => $id, 'role_id' => 1]);
        $this->migration()->up();

        $this->assertSame('the page', $this->openPageAs(1)->getContent());
        $denied = $this->openPageAs(2);
        $this->assertTrue($denied->isRedirect());
        $this->assertStringEndsWith('/dashboard', $denied->headers->get('Location'));
    }

    public function test_roles_keep_the_access_they_had_because_grants_follow_the_row_not_the_address(): void
    {
        $id = $this->menuRow('SHA Rates', self::OLD_PAGE);
        DB::table('cb_sub_module_permissions')->insert([['sub_module_id' => $id, 'role_id' => 1], ['sub_module_id' => $id, 'role_id' => 3]]);

        $this->migration()->up();

        $this->assertSame([1, 3], DB::table('cb_sub_module_permissions')->where('sub_module_id', $id)->orderBy('role_id')->pluck('role_id')->all());
    }
}
