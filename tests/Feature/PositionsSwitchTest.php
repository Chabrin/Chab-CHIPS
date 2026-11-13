<?php

namespace Tests\Feature;

use App\Http\Controllers\HrSettingsController;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Staff positions can be switched on or off (off: no longer offered for new staff or contracts, kept by those who hold it),
 * and deleting one that is in use says what uses it and suggests switching it off.
 */
class PositionsSwitchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_staff', function (Blueprint $t) { $t->id(); $t->integer('position_id')->nullable(); });
        Schema::create('sys_set_departments', function (Blueprint $t) { $t->id(); $t->string('department_name'); $t->dateTime('date_time')->nullable(); });
        Schema::create('sys_gl_chart_accounts_master', function (Blueprint $t) { $t->id(); $t->string('account_name')->nullable(); });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) {
            $t->id();
            $t->dateTime('date_time')->nullable();
            $t->string('position_name');
            $t->decimal('basic_salary', 12, 2)->nullable();
            $t->integer('salary_account_id')->nullable();
            $t->integer('current_status_id')->nullable();
            $t->integer('reports_to_id')->nullable();
            $t->string('slug_name')->nullable();
            $t->integer('department_id')->nullable();
            $t->boolean('is_active')->default(true);
        });
        // The records a position can be on
        foreach (['sys_hr_employee_contracts', 'sys_hr_payroll_summary', 'sys_hr_leave_requests', 'discipline_cases', 'staff_onboarding_invitations'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->integer('position_id')->nullable(); });
        }
        Schema::create('sys_hr_payroll_elements_repeat_staff_positions_id', function (Blueprint $t) { $t->id(); $t->integer('staff_positions_id')->nullable(); });

        DB::table('sys_set_staff_positions')->insert([
            ['id' => 1, 'position_name' => 'Field Officer', 'is_active' => true],
            ['id' => 2, 'position_name' => 'Zone Manager', 'is_active' => true],
            ['id' => 3, 'position_name' => 'Audit Assistant', 'is_active' => true],
            ['id' => 4, 'position_name' => 'Old Test Position', 'is_active' => false],
        ]);
    }

    private function actingAsWho(string ...$permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = 7;
        $this->actingAs($user);
    }

    private function controller(): HrSettingsController
    {
        return app(HrSettingsController::class);
    }

    private function use(string $table, string $column, int $positionId, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            DB::table($table)->insert([$column => $positionId]);
        }
    }

    // ── switching on and off ──────────────────────────────────────────────

    public function test_a_new_position_is_on_and_only_those_that_are_on_are_offered(): void
    {
        $this->assertSame(['Audit Assistant', 'Field Officer', 'Zone Manager'], Position::active()->orderBy('position_name')->pluck('position_name')->all());
    }

    public function test_switching_a_position_off_leaves_it_out_but_keeps_it_for_the_staff_who_hold_it(): void
    {
        $this->actingAsWho('update_staff_positions');
        $this->use('sys_staff', 'position_id', 1, 3);

        $response = $this->controller()->positionsToggleAjax(1);

        $this->assertTrue($response->getData(true)['success']);
        $this->assertFalse($response->getData(true)['is_active']);
        $this->assertSame('Field Officer is switched off. It is no longer offered for new staff or contracts, and the 3 staff holding it keep it.', $response->getData(true)['message']);
        $this->assertNotContains('Field Officer', Position::active()->pluck('position_name')->all());
        $this->assertSame(3, DB::table('sys_staff')->where('position_id', 1)->count());
    }

    public function test_switching_an_unused_position_off_does_not_mention_staff(): void
    {
        $this->actingAsWho('update_staff_positions');

        $this->assertSame('Audit Assistant is switched off. It is no longer offered for new staff or contracts.', $this->controller()->positionsToggleAjax(3)->getData(true)['message']);
    }

    public function test_switching_a_position_back_on_offers_it_again(): void
    {
        $this->actingAsWho('update_staff_positions');

        $response = $this->controller()->positionsToggleAjax(4)->getData(true);

        $this->assertTrue($response['is_active']);
        $this->assertSame('Old Test Position is on again and can be chosen for new staff.', $response['message']);
        $this->assertContains('Old Test Position', Position::active()->pluck('position_name')->all());
    }

    public function test_switching_needs_the_update_permission(): void
    {
        $this->actingAsWho('read_staff_positions');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            $this->controller()->positionsToggleAjax(1);
        } finally {
            $this->assertTrue(Position::find(1)->is_active, 'nothing changed');
        }
    }

    public function test_switching_a_missing_position_is_a_404(): void
    {
        $this->actingAsWho('update_staff_positions');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->controller()->positionsToggleAjax(999);
    }

    // ── the report-to choices ─────────────────────────────────────────────

    public function test_only_positions_that_are_on_can_be_reported_to_besides_the_current_boss(): void
    {
        DB::table('sys_set_staff_positions')->where('id', 3)->update(['reports_to_id' => 4]);

        $names = fn (?int $current) => collect($this->controller()->positionsDropdowns(Request::create('/', 'GET', ['current_id' => $current]))->getData(true)['positions'])->pluck('position_name')->all();

        $this->assertSame(['Audit Assistant', 'Field Officer', 'Zone Manager'], $names(null), 'the switched-off one is not offered');
        $this->assertSame(['Field Officer', 'Old Test Position', 'Zone Manager'], $names(3), 'but the boss this position already has stays in its list');
    }

    // ── deleting ──────────────────────────────────────────────────────────

    private function deleteMessage(int $id): array
    {
        $this->actingAsWho('delete_staff_positions');
        $response = $this->controller()->positionsDestroyAjax($id);

        return ['status' => $response->getStatusCode()] + $response->getData(true);
    }

    public function test_deleting_a_position_that_staff_hold_names_how_many_and_suggests_switching_it_off(): void
    {
        $this->use('sys_staff', 'position_id', 1, 3);

        $result = $this->deleteMessage(1);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Cannot delete this position because 3 staff members hold it. Mark it as inactive instead.', $result['error']);
        $this->assertNotNull(Position::find(1));
    }

    public function test_one_staff_member_is_named_in_the_singular(): void
    {
        $this->use('sys_staff', 'position_id', 1, 1);

        $this->assertSame('Cannot delete this position because 1 staff member holds it. Mark it as inactive instead.', $this->deleteMessage(1)['error']);
    }

    public function test_deleting_a_position_other_positions_report_to_says_so(): void
    {
        DB::table('sys_set_staff_positions')->where('id', 1)->update(['reports_to_id' => 2]);

        $this->assertSame('Cannot delete this position because 1 other position reports to it. Mark it as inactive instead.', $this->deleteMessage(2)['error']);
    }

    public function test_a_position_on_contracts_payroll_leave_or_discipline_records_cannot_be_deleted_either(): void
    {
        // These used to reach the database and fail with a general "Failed to delete position"
        $this->use('sys_hr_employee_contracts', 'position_id', 1, 4);
        $this->use('sys_hr_payroll_summary', 'position_id', 1, 1);
        $this->use('sys_hr_leave_requests', 'position_id', 1, 2);
        $this->use('discipline_cases', 'position_id', 1, 1);
        $this->use('sys_hr_payroll_elements_repeat_staff_positions_id', 'staff_positions_id', 1, 1);
        $this->use('staff_onboarding_invitations', 'position_id', 1, 2);

        $this->assertSame(
            'Cannot delete this position because it is on 4 contracts, it is on 1 payroll record, it is on 2 leave requests, it is on 1 discipline case, '
            . 'a payroll element is set up for it and 2 staff onboardings name it. Mark it as inactive instead.',
            $this->deleteMessage(1)['error']
        );
        $this->assertNotNull(Position::find(1));
    }

    public function test_several_reasons_are_joined_in_one_sentence(): void
    {
        $this->use('sys_staff', 'position_id', 1, 2);
        $this->use('sys_hr_employee_contracts', 'position_id', 1, 1);

        $this->assertSame('Cannot delete this position because 2 staff members hold it and it is on 1 contract. Mark it as inactive instead.', $this->deleteMessage(1)['error']);
    }

    public function test_deleting_an_unused_position_says_it_worked(): void
    {
        $result = $this->deleteMessage(3);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['success']);
        $this->assertSame('Position deleted successfully', $result['message']);
        $this->assertNull(Position::find(3));
    }

    // ── the clean-up command ──────────────────────────────────────────────

    private function legacyPositions(): void
    {
        DB::table('sys_set_staff_positions')->insert([
            ['id' => 10, 'position_name' => 'FIELD MANAGER', 'is_active' => true, 'slug_name' => 'field_manager'],
            ['id' => 11, 'position_name' => 'Secretary', 'is_active' => true, 'slug_name' => 'secretary'],
            ['id' => 12, 'position_name' => 'Zone Supervisor', 'is_active' => true, 'slug_name' => 'zone_supervisor'],
            ['id' => 13, 'position_name' => 'Security Guard', 'is_active' => true, 'slug_name' => 'security_guard'],
        ]);
        $this->use('sys_staff', 'position_id', 10, 6);
    }

    public function test_the_cleanup_shows_what_it_would_do_and_changes_nothing_by_default(): void
    {
        $this->legacyPositions();

        $this->artisan('hr:positions-cleanup')
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();

        $this->assertSame(8, Position::count());
        $this->assertTrue(Position::find(10)->is_active);
    }

    public function test_the_cleanup_plan_names_what_is_kept_deleted_and_switched_off_and_why(): void
    {
        $this->legacyPositions();

        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('hr:positions-cleanup'));
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertMatchesRegularExpression('/FIELD MANAGER\s*\|\s*switch off\s*\|\s*Not approved, but 6 staff members hold it/', $output);
        $this->assertMatchesRegularExpression('/Secretary\s*\|\s*delete\s*\|\s*Not approved, and nothing uses it/', $output);
        $this->assertMatchesRegularExpression('/Zone Supervisor\s*\|\s*keep\s*\|\s*The zone pages find staff by this position/', $output);
        $this->assertMatchesRegularExpression('/Field Officer\s*\|\s*keep\s*\|\s*In the approved list/', $output);
        $this->assertStringContainsString('3 to delete, 1 to switch off, 4 kept.', $output);
    }

    public function test_applying_the_cleanup_switches_off_used_positions_and_deletes_unused_ones(): void
    {
        $this->legacyPositions();

        $this->artisan('hr:positions-cleanup', ['--apply' => true])->expectsOutputToContain('Done.')->assertSuccessful();

        $this->assertFalse(Position::find(10)->is_active, 'six staff hold FIELD MANAGER, so it is switched off, not deleted');
        $this->assertSame(6, DB::table('sys_staff')->where('position_id', 10)->count(), 'and they keep it');
        $this->assertNull(Position::find(11), 'Secretary is not approved and nothing uses it');
        $this->assertNull(Position::find(13), 'so is Security Guard');
        $this->assertNull(Position::find(4), 'and the old test position');
    }

    public function test_the_approved_positions_and_their_older_names_are_left_alone(): void
    {
        $this->artisan('hr:positions-cleanup', ['--apply' => true])->assertSuccessful();

        $this->assertNotNull(Position::find(1), 'Field Officer is in the approved list');
        $this->assertNotNull(Position::find(2), 'Zone Manager is in the approved list');
        $this->assertNotNull(Position::find(3), 'Audit Assistant is in the approved list');
        $this->assertTrue(Position::find(1)->is_active);
    }

    public function test_positions_the_zone_pages_look_up_by_slug_are_never_touched(): void
    {
        $this->legacyPositions();

        $this->artisan('hr:positions-cleanup', ['--apply' => true])->assertSuccessful();

        $this->assertNotNull(Position::find(12));
        $this->assertTrue(Position::find(12)->is_active);
    }

    public function test_a_name_given_with_keep_is_left_as_it_is(): void
    {
        $this->legacyPositions();

        $this->artisan('hr:positions-cleanup', ['--apply' => true, '--keep' => ['Security Guard', 'field manager']])->assertSuccessful();

        $this->assertNotNull(Position::find(13), 'kept by name');
        $this->assertTrue(Position::find(10)->is_active, 'the name match ignores capitals');
        $this->assertNull(Position::find(11), 'only the names given are kept');
    }

    public function test_a_position_already_switched_off_is_not_switched_off_again(): void
    {
        $this->legacyPositions();
        DB::table('sys_set_staff_positions')->where('id', 10)->update(['is_active' => false]);

        $this->artisan('hr:positions-cleanup')->expectsOutputToContain('Already switched off')->assertSuccessful();
    }

    public function test_a_clean_up_that_fails_part_way_changes_nothing(): void
    {
        $this->legacyPositions();
        // Deleting Security Guard fails, after Old Test Position and Secretary were already deleted
        DB::unprepared("CREATE TRIGGER stop_delete BEFORE DELETE ON sys_set_staff_positions WHEN OLD.id = 13 BEGIN SELECT RAISE(ABORT, 'stopped'); END;");

        $failed = false;
        try {
            \Illuminate\Support\Facades\Artisan::call('hr:positions-cleanup', ['--apply' => true]);
        } catch (\Throwable $e) {
            $failed = true;
        }

        $this->assertTrue($failed, 'the failure is not hidden');
        $this->assertNotNull(Position::find(4), 'the earlier deletes were undone');
        $this->assertNotNull(Position::find(11));
        $this->assertTrue(Position::find(10)->is_active, 'and nothing was switched off');
    }

    public function test_deleting_needs_the_delete_permission(): void
    {
        $this->actingAsWho('read_staff_positions', 'update_staff_positions');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            $this->controller()->positionsDestroyAjax(3);
        } finally {
            $this->assertNotNull(Position::find(3), 'nothing was deleted');
        }
    }
}
