<?php

namespace Tests\Feature;

use App\Http\Controllers\LeaveManagementController;
use App\Http\Controllers\WorkflowApprovalController;
use App\Jobs\SendApprovalNotification;
use App\Models\ConfirmationLog;
use App\Models\LeaveCalendarPlan;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDaysTaken;
use App\Models\Notification;
use App\Models\User;
use App\Services\Leave\LeaveApprovalRouter;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Position-based leave approval: who a request goes to, who may act on it, and what each stage does.
 *
 * Org used throughout (reports to): Field Officer -> Senior Field Officer -> Zone Manager. Staff 10 and 11 are field
 * officers, 20 is the senior field officer (has a system user), 30 the zone manager (has a system user), 40 holds a
 * position nobody reports to and that reports to nobody, 50 has no position, and 60 is an HR manager.
 */
class LeaveApprovalRoutingTest extends TestCase
{
    private const POS_FIELD_OFFICER = 1;
    private const POS_SENIOR = 2;
    private const POS_ZONE_MANAGER = 3;
    private const POS_LONE = 4;
    private const POS_HR = 5;
    private const POS_ORPHAN_LEAF = 6;
    private const POS_ORPHAN_BOSS = 7;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        $this->createTables();
        $this->seedOrg();
    }

    private function createTables(): void
    {
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('full_names')->nullable();
            $t->integer('position_id')->nullable();
            $t->integer('department_id')->nullable();
            $t->integer('current_status_id')->default(1);
            $t->string('hire_date')->nullable();
        });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) {
            $t->id();
            $t->string('position_name');
            $t->integer('reports_to_id')->nullable();
            $t->integer('department_id')->nullable();
        });
        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->integer('block')->default(0);
            $t->integer('staff_id')->nullable();
            $t->rememberToken();
        });
        Schema::create('sys_user_staff_links', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->integer('staff_id');
        });
        Schema::create('cb_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('display_name')->nullable();
            $t->string('description')->nullable();
            $t->timestamps();
        });
        Schema::create('cb_permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('display_name')->nullable();
            $t->string('description')->nullable();
            $t->timestamps();
        });
        Schema::create('cb_role_user', function (Blueprint $t) {
            $t->integer('role_id');
            $t->integer('user_id');
            $t->string('user_type');
        });
        Schema::create('cb_permission_user', function (Blueprint $t) {
            $t->integer('permission_id');
            $t->integer('user_id');
            $t->string('user_type');
        });
        Schema::create('cb_permission_role', function (Blueprint $t) {
            $t->integer('permission_id');
            $t->integer('role_id');
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->string('type')->nullable();
            $t->string('notifiable_type');
            $t->integer('notifiable_id');
            $t->text('data')->nullable();
            $t->string('title')->nullable();
            $t->text('message')->nullable();
            $t->string('action_url')->nullable();
            $t->dateTime('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sys_hr_leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('type_name');
            $t->decimal('entitled_days', 8, 2)->nullable();
            $t->boolean('counts_weekends')->default(false);
            $t->boolean('counts_public_holidays')->default(false);
        });
        Schema::create('sys_hr_leave_requests', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id');
            $t->integer('leave_type_id');
            $t->dateTime('start_date');
            $t->dateTime('end_date');
            $t->integer('current_status_id')->default(1);
            $t->decimal('used_days', 8, 2)->nullable();
            $t->integer('status_changed_by')->nullable();
            $t->integer('position_id')->nullable();
            $t->decimal('days', 8, 2)->nullable();
            $t->decimal('requested_days', 8, 2)->nullable();
            $t->decimal('entitled_days', 8, 2)->nullable();
            $t->decimal('days_balance', 8, 2)->nullable();
            $t->decimal('amount', 12, 2)->nullable();
            $t->integer('allowance_type_id')->nullable();
            $t->integer('created_by_id')->nullable();
            $t->dateTime('date_time')->nullable();
        });
        Schema::create('sys_hr_leave_requests_234_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->integer('current_status_id')->default(1);
            $t->dateTime('leave_date');
            $t->integer('day_type_id')->nullable();
            $t->decimal('day_fraction', 3, 2)->default(1);
            $t->decimal('pay_rate', 5, 2)->nullable();
            $t->integer('tier_order')->nullable();
        });
        Schema::create('sys_hr_leave_calendar', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->integer('leave_type_id');
            $t->dateTime('start_date');
            $t->dateTime('end_date')->nullable();
            $t->decimal('entitled_days', 8, 2)->nullable();
            $t->decimal('balance', 8, 2)->nullable();
            $t->integer('current_status_id')->default(5);
            $t->integer('created_by')->nullable();
            $t->dateTime('date_time')->nullable();
        });
        Schema::create('sys_hr_leave_type_tiers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('leave_type_id');
            $t->unsignedSmallInteger('tier_order');
            $t->string('label');
            $t->unsignedInteger('days')->nullable();
            $t->decimal('pay_rate', 5, 2);
        });
        Schema::create('sys_hr_public_holidays', function (Blueprint $t) {
            $t->id();
            $t->string('title')->nullable();
            $t->dateTime('start_date')->nullable();
            $t->dateTime('end_date')->nullable();
            $t->string('recurrent')->nullable();
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('sys_hr_working_days', function (Blueprint $t) {
            $t->id();
            $t->string('week_day')->nullable();
            $t->string('work_type_id')->nullable();
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('confirmation_logs', function (Blueprint $t) {
            $t->id();
            $t->string('section');
            $t->string('model')->nullable();
            $t->string('action');
            $t->text('ids');
            $t->integer('confirmed_by')->nullable();
            $t->dateTime('confirmed_at')->nullable();
            $t->integer('approval_stage')->nullable();
            $t->text('metadata')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Positions, staff, system users and roles. User ids equal the staff id they are linked to, plus 1000.
     */
    private function seedOrg(): void
    {
        DB::table('sys_set_staff_positions')->insert([
            ['id' => self::POS_FIELD_OFFICER, 'position_name' => 'Field Officer', 'reports_to_id' => self::POS_SENIOR],
            ['id' => self::POS_SENIOR, 'position_name' => 'Senior Field Officer', 'reports_to_id' => self::POS_ZONE_MANAGER],
            ['id' => self::POS_ZONE_MANAGER, 'position_name' => 'Zone Manager', 'reports_to_id' => null],
            ['id' => self::POS_LONE, 'position_name' => 'Lone Position', 'reports_to_id' => null],
            ['id' => self::POS_HR, 'position_name' => 'Operations & HR Manager', 'reports_to_id' => null],
            // Reports to a position nobody holds
            ['id' => self::POS_ORPHAN_LEAF, 'position_name' => 'Accountant', 'reports_to_id' => self::POS_ORPHAN_BOSS],
            ['id' => self::POS_ORPHAN_BOSS, 'position_name' => 'Chief Accountant', 'reports_to_id' => null],
        ]);

        DB::table('sys_staff')->insert([
            ['id' => 10, 'full_names' => 'Field Officer One', 'position_id' => self::POS_FIELD_OFFICER],
            ['id' => 11, 'full_names' => 'Field Officer Two', 'position_id' => self::POS_FIELD_OFFICER],
            ['id' => 20, 'full_names' => 'Senior Officer', 'position_id' => self::POS_SENIOR],
            ['id' => 30, 'full_names' => 'Zone Manager', 'position_id' => self::POS_ZONE_MANAGER],
            ['id' => 40, 'full_names' => 'Lone Worker', 'position_id' => self::POS_LONE],
            ['id' => 50, 'full_names' => 'No Position', 'position_id' => null],
            ['id' => 60, 'full_names' => 'HR Manager', 'position_id' => self::POS_HR],
            ['id' => 70, 'full_names' => 'Accountant', 'position_id' => self::POS_ORPHAN_LEAF],
            ['id' => 80, 'full_names' => 'Director Staff', 'position_id' => null],
        ]);

        // System users linked to staff 20, 30, 60 and 80, plus an unlinked-by-staff field officer user for staff 10
        foreach ([10, 20, 30, 60, 80] as $staffId) {
            DB::table('josya_users')->insert(['id' => 1000 + $staffId, 'name' => "User {$staffId}", 'staff_id' => $staffId]);
            DB::table('sys_user_staff_links')->insert(['user_id' => 1000 + $staffId, 'staff_id' => $staffId]);
        }

        DB::table('cb_roles')->insert([['id' => 1, 'name' => 'hr_manager'], ['id' => 2, 'name' => 'directors']]);
        DB::table('cb_role_user')->insert([
            ['role_id' => 1, 'user_id' => 1060, 'user_type' => User::class],
            ['role_id' => 2, 'user_id' => 1080, 'user_type' => User::class],
        ]);

        // Directors hold the leave-application permissions, as in the real data
        foreach (['read', 'create', 'update', 'delete'] as $i => $action) {
            DB::table('cb_permissions')->insert(['id' => $i + 1, 'name' => "{$action}_leave_applications"]);
            DB::table('cb_permission_role')->insert(['permission_id' => $i + 1, 'role_id' => 2]);
        }

        DB::table('sys_hr_leave_types')->insert(['id' => 2, 'type_name' => 'Normal']);
        foreach ([10, 11, 20, 30, 40, 50, 60, 70] as $staffId) {
            DB::table('sys_hr_leave_calendar')->insert([
                'employee_id' => $staffId,
                'leave_type_id' => 2,
                'start_date' => '2026-01-01 00:00:00',
                'entitled_days' => 21,
                'balance' => 21,
                'current_status_id' => 5,
            ]);
        }
    }

    private function user(int $staffId): User
    {
        return User::findOrFail(1000 + $staffId);
    }

    private function leave(int $staffId, int $status = 1): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => $staffId,
            'leave_type_id' => 2,
            'start_date' => '2026-10-19 08:00:00',
            'end_date' => '2026-10-21 17:00:00', // Mon-Wed, 3 days
            'current_status_id' => $status,
        ]);
    }

    private function service(): LeaveApprovalService
    {
        return app(LeaveApprovalService::class);
    }

    public function test_a_field_officer_with_a_reachable_supervisor_goes_to_the_supervisor_first(): void
    {
        $request = $this->leave(10);

        $outcome = $this->service()->submit($request, 1010);

        $this->assertSame(LeaveApprovalRouter::ROUTE_SUPERVISOR, $outcome['route']);
        $this->assertSame(1, $request->fresh()->current_status_id);
        $this->assertSame(0, ConfirmationLog::where('action', 'route')->count());
    }

    public function test_a_supervisor_goes_straight_to_hr_and_the_skipped_stage_is_logged(): void
    {
        $request = $this->leave(20); // Senior Field Officer: the Field Officer position reports to theirs

        $outcome = $this->service()->submit($request, 1020);

        $this->assertSame(LeaveApprovalRouter::ROUTE_HR, $outcome['route']);
        $this->assertSame('is_supervisor', $outcome['reason']);
        $this->assertSame(24, $request->fresh()->current_status_id);
        $log = ConfirmationLog::where('action', 'route')->first();
        $this->assertSame('supervisor_skipped', $log->metadata['stage']);
        $this->assertSame('is_supervisor', $log->metadata['reason']);
    }

    public function test_staff_with_no_position_go_straight_to_hr(): void
    {
        $outcome = $this->service()->submit($this->leave(50), 1050);

        $this->assertSame('no_position', $outcome['reason']);
        $this->assertSame(24, $outcome['status']);
    }

    public function test_a_position_that_reports_to_nobody_goes_straight_to_hr(): void
    {
        $outcome = $this->service()->submit($this->leave(40), 1040);

        $this->assertSame('no_boss_position', $outcome['reason']);
        $this->assertSame(24, $outcome['status']);
    }

    public function test_a_boss_position_with_nobody_in_it_goes_straight_to_hr(): void
    {
        $outcome = $this->service()->submit($this->leave(70), 1070);

        $this->assertSame('no_supervisor_with_system_access', $outcome['reason']);
        $this->assertSame(24, $outcome['status']);
    }

    public function test_a_supervisor_without_a_system_user_does_not_count(): void
    {
        DB::table('sys_user_staff_links')->where('staff_id', 20)->delete();

        $outcome = $this->service()->submit($this->leave(10), 1010);

        $this->assertSame('no_supervisor_with_system_access', $outcome['reason']);
        $this->assertSame(24, $outcome['status']);
    }

    public function test_a_blocked_supervisor_user_does_not_count(): void
    {
        DB::table('josya_users')->where('id', 1020)->update(['block' => 1]);

        $outcome = $this->service()->submit($this->leave(10), 1010);

        $this->assertSame(LeaveApprovalRouter::ROUTE_HR, $outcome['route']);
    }

    public function test_the_hr_approvers_own_leave_is_approved_automatically_and_the_directors_are_told(): void
    {
        $request = $this->leave(60);

        $outcome = $this->service()->submit($request, 1060);

        $this->assertTrue($outcome['auto_approved']);
        $this->assertSame(5, $request->fresh()->current_status_id);
        $this->assertEquals(3, $request->fresh()->used_days);
        $this->assertEquals(18, LeaveCalendarPlan::where('employee_id', 60)->value('balance'));
        $this->assertSame(3, LeaveRequestDaysTaken::where('parent_id', $request->id)->count());
        $approval = ConfirmationLog::where('action', 'approve')->first();
        $this->assertTrue($approval->metadata['auto_approved']);
        $this->assertSame(1, Notification::where('type', 'LeaveAutoApproved')->where('notifiable_id', 1080)->count());
        Bus::assertDispatched(SendApprovalNotification::class);
    }

    public function test_the_supervisor_stage_records_the_approver_charges_nothing_and_sends_no_email(): void
    {
        $request = $this->leave(10);

        $result = $this->service()->approve($request, $this->user(20));

        $this->assertTrue($result['success']);
        $fresh = $request->fresh();
        $this->assertSame(24, $fresh->current_status_id);
        $this->assertSame(1020, $fresh->status_changed_by);
        $this->assertSame(0, LeaveRequestDaysTaken::count());
        $this->assertEquals(21, LeaveCalendarPlan::where('employee_id', 10)->value('balance'));
        Bus::assertNotDispatched(SendApprovalNotification::class);
    }

    public function test_hr_final_approval_charges_the_days_and_notifies(): void
    {
        $request = $this->leave(10);
        $this->service()->approve($request, $this->user(20));

        $result = $this->service()->approve($request->fresh(), $this->user(60));

        $this->assertTrue($result['success']);
        $this->assertSame(5, $request->fresh()->current_status_id);
        $this->assertEquals(3, $request->fresh()->used_days);
        $this->assertEquals(18, LeaveCalendarPlan::where('employee_id', 10)->value('balance'));
        Bus::assertDispatched(SendApprovalNotification::class);
    }

    public function test_hr_cannot_skip_a_supervisor_who_can_be_reached(): void
    {
        $request = $this->leave(10);

        $result = $this->service()->approve($request, $this->user(60));

        $this->assertFalse($result['success']);
        $this->assertSame(1, $request->fresh()->current_status_id);
    }

    public function test_a_supervisor_from_another_branch_of_the_tree_cannot_approve(): void
    {
        $request = $this->leave(10);

        // Staff 30 (Zone Manager) is a supervisor, but of Senior Field Officers, not Field Officers
        $result = $this->service()->approve($request, $this->user(30));

        $this->assertFalse($result['success']);
        $this->assertSame(1, $request->fresh()->current_status_id);
    }

    public function test_nobody_approves_their_own_leave(): void
    {
        // Staff 20 is a supervisor, so their request sits with HR; staff 60 is the HR manager but it is staff 20's leave
        $own = $this->leave(20, 24);

        $this->assertNotNull(app(LeaveApprovalRouter::class)->canAct($this->user(20), $own));
        $this->assertFalse($this->service()->approve($own, $this->user(20))['success']);

        // And a director with a staff record cannot approve their own
        $directorsOwn = $this->leave(80, 24);
        $this->assertSame('You cannot approve your own leave application.', app(LeaveApprovalRouter::class)->canAct($this->user(80), $directorsOwn));
    }

    public function test_the_same_person_cannot_give_both_stages(): void
    {
        // Staff 80 is a director (an HR-stage approver) who also gave the stage-1 approval
        $request = $this->leave(10, 24);
        $request->status_changed_by = 1080;
        $request->save();

        $result = $this->service()->approve($request, $this->user(80));

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('different approver', $result['error']);
        $this->assertSame(24, $request->fresh()->current_status_id);
    }

    public function test_hr_can_finish_a_request_filed_before_routing_existed_that_only_waits_on_hr(): void
    {
        $legacy = $this->leave(50, 1); // no position, still at status 1

        $result = $this->service()->approve($legacy, $this->user(60));

        $this->assertTrue($result['success']);
        $this->assertSame(5, $legacy->fresh()->current_status_id);
        $this->assertSame(1, ConfirmationLog::where('action', 'route')->count());
    }

    public function test_the_supervisor_can_reject_and_a_stranger_cannot(): void
    {
        $request = $this->leave(10);

        $this->assertFalse($this->service()->reject($request, $this->user(30))['success']);
        $this->assertTrue($this->service()->reject($request, $this->user(20))['success']);
        $this->assertSame(41, $request->fresh()->current_status_id);
    }

    public function test_a_stale_copy_cannot_approve_twice(): void
    {
        $request = $this->leave(50, 24);
        $stale = LeaveRequest::find($request->id);

        $first = $this->service()->hrApprove($request, 1060);
        $second = $this->service()->hrApprove($stale, 1060); // loaded before the first approval landed

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
        $this->assertEquals(18, LeaveCalendarPlan::where('employee_id', 50)->value('balance'));
        $this->assertSame(1, ConfirmationLog::where('action', 'approve')->count());
    }

    public function test_each_user_only_sees_the_requests_they_can_act_on(): void
    {
        $fieldOfficerOne = $this->leave(10);            // waits on a Senior Field Officer
        $seniorOfficer = $this->leave(20);              // supervisor: straight to HR (filed at status 1 before routing)
        $partial = $this->leave(11, 24);                // already recommended: HR stage
        $router = app(LeaveApprovalRouter::class);

        $this->assertEquals([$fieldOfficerOne->id], $router->actionableFor($this->user(20))->pluck('id')->all());
        $this->assertEquals([], $router->actionableFor($this->user(30))->pluck('id')->all());
        $this->assertEquals([], $router->actionableFor($this->user(10))->pluck('id')->all());
        $this->assertEquals([$seniorOfficer->id, $partial->id], $router->actionableFor($this->user(60))->pluck('id')->all());
    }

    public function test_the_approvals_page_lists_leave_by_position_and_hides_it_from_others(): void
    {
        $request = $this->leave(10);
        $controller = app(WorkflowApprovalController::class);

        $this->actingAs($this->user(20));
        $mine = json_decode($controller->approvalsList(Request::create('/', 'GET', ['section' => 'Leave Application']))->getContent(), true)['records'];
        $this->assertSame([$request->id], array_column($mine, 'id'));
        $this->assertSame('Stage 1 (Supervisor approval)', $mine[0]['status']);

        $this->actingAs($this->user(30));
        $theirs = json_decode($controller->approvalsList(Request::create('/', 'GET', ['section' => 'Leave Application']))->getContent(), true)['records'];
        $this->assertSame([], $theirs);
    }

    public function test_the_approve_endpoint_enforces_routing(): void
    {
        $request = $this->leave(10);
        $controller = app(WorkflowApprovalController::class);

        $this->actingAs($this->user(30));
        $denied = $controller->approve(Request::create('/', 'POST', ['section' => 'Leave Application']), $request->id);
        $this->assertSame(403, $denied->getStatusCode());
        $this->assertSame(1, $request->fresh()->current_status_id);

        $this->actingAs($this->user(20));
        $allowed = $controller->approve(Request::create('/', 'POST', ['section' => 'Leave Application']), $request->id);
        $this->assertSame(200, $allowed->getStatusCode());
        $this->assertSame(24, $request->fresh()->current_status_id);
    }

    public function test_the_days_preview_is_only_available_to_someone_who_can_act(): void
    {
        $request = $this->leave(10);
        $controller = app(WorkflowApprovalController::class);

        $this->actingAs($this->user(30));
        $this->assertSame(403, $controller->getLeaveApplicationDays($request->id)->getStatusCode());

        $this->actingAs($this->user(20));
        $response = $controller->getLeaveApplicationDays($request->id);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals(3, json_decode($response->getContent(), true)['total_days']);
    }

    public function test_only_the_employee_can_download_their_leave_pass(): void
    {
        $approved = $this->leave(10, 5);

        $this->actingAs($this->user(60)); // HR, but not the employee
        $response = app(LeaveManagementController::class)->downloadLeavePass(Crypt::encrypt($approved->id));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('your own leave pass', $response->getContent());
    }

    public function test_automatic_approval_only_applies_when_the_hr_approver_filed_it_themselves(): void
    {
        $request = $this->leave(60);

        // Filed by a director (user 1080), not by the HR manager (user 1060)
        $outcome = $this->service()->submit($request, 1080);

        $this->assertFalse($outcome['auto_approved']);
        $this->assertSame('filed_on_behalf', $outcome['reason']);
        $this->assertSame(24, $request->fresh()->current_status_id);
        $this->assertSame(0, Notification::where('type', 'LeaveAutoApproved')->count());
    }

    public function test_a_user_cannot_file_leave_for_another_staff_member(): void
    {
        $this->actingAs($this->user(10)); // a field officer
        $request = Request::create('/', 'POST', [
            'staff_id' => 60, // the HR manager
            'leave_type_id' => 2,
            'available_days' => 10,
            'leave_duration_type' => 'full_day',
            'start_date' => '2026-12-07',
            'requested_days' => 2,
        ]);

        $response = app(LeaveManagementController::class)->leaveApplicationsStore($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_a_user_cannot_edit_another_staff_members_application(): void
    {
        $someoneElses = $this->leave(60);

        $this->actingAs($this->user(10));
        $request = Request::create('/', 'POST', [
            'staff_id' => 10,
            'leave_type_id' => 2,
            'available_days' => 10,
            'leave_duration_type' => 'full_day',
            'start_date' => '2026-12-07',
            'requested_days' => 2,
        ]);

        $response = app(LeaveManagementController::class)->leaveApplicationsUpdate($request, $someoneElses->id);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_bulk_approval_routes_are_not_swallowed_by_the_single_item_routes(): void
    {
        $routes = app('router')->getRoutes();

        $bulk = $routes->match(Request::create('/ajax/workflow/approval/bulk/approve', 'POST'));
        $single = $routes->match(Request::create('/ajax/workflow/approval/12/approve', 'POST'));

        $this->assertSame('ajax.workflow.approval.bulk.approve', $bulk->getName());
        $this->assertSame('ajax.workflow.approval.approve', $single->getName());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $routes->match(Request::create('/ajax/leave-management/leave-applications/status-update', 'POST'));
    }

    public function test_the_days_preview_for_a_missing_request_is_a_404(): void
    {
        $this->actingAs($this->user(20));

        $this->assertSame(404, app(WorkflowApprovalController::class)->getLeaveApplicationDays(9999)->getStatusCode());
    }

    public function test_a_bad_leave_pass_id_is_a_404_not_a_500(): void
    {
        $this->actingAs($this->user(10));

        $this->assertSame(404, app(LeaveManagementController::class)->downloadLeavePass('not-an-encrypted-id')->getStatusCode());
        $this->assertSame(404, app(LeaveManagementController::class)->downloadLeavePass(Crypt::encrypt(9999))->getStatusCode());
    }

    public function test_the_leave_list_only_shows_your_own_requests_unless_you_can_read_everyones(): void
    {
        $mine = $this->leave(10);
        $theirs = $this->leave(11);
        $controller = app(LeaveManagementController::class);

        $this->actingAs($this->user(10)); // a field officer
        $own = json_decode($controller->leaveApplicationsList()->getContent(), true)['leave_applications'];
        $this->assertSame([$mine->id], array_column($own, 'id'));

        $this->actingAs($this->user(80)); // a director
        $all = json_decode($controller->leaveApplicationsList()->getContent(), true)['leave_applications'];
        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], array_column($all, 'id'));
    }

    public function test_the_leave_detail_page_is_closed_to_other_staff(): void
    {
        $theirs = $this->leave(11);

        $this->actingAs($this->user(10));
        $response = app(LeaveManagementController::class)->leaveApplicationsByStaff(Crypt::encrypt($theirs->id));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_approved_leave_cannot_be_deleted(): void
    {
        $approved = $this->leave(10, 5);

        $this->actingAs($this->user(80)); // even a director with delete permission
        $response = app(LeaveManagementController::class)->leaveApplicationsDelete($approved->id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNotNull(LeaveRequest::find($approved->id));
    }

    public function test_you_can_delete_your_own_pending_request_but_not_someone_elses(): void
    {
        $mine = $this->leave(10);
        $theirs = $this->leave(11);
        $controller = app(LeaveManagementController::class);

        $this->actingAs($this->user(10));
        $this->assertSame(403, $controller->leaveApplicationsDelete($theirs->id)->getStatusCode());
        $this->assertNotNull(LeaveRequest::find($theirs->id));
        $this->assertSame(200, $controller->leaveApplicationsDelete($mine->id)->getStatusCode());
        $this->assertNull(LeaveRequest::find($mine->id));
    }

    public function test_a_director_can_delete_a_pending_or_rejected_request_and_a_missing_one_is_a_404(): void
    {
        $pending = $this->leave(10);
        $rejected = $this->leave(11, 41);
        $controller = app(LeaveManagementController::class);

        $this->actingAs($this->user(80));
        $this->assertSame(200, $controller->leaveApplicationsDelete($pending->id)->getStatusCode());
        $this->assertSame(200, $controller->leaveApplicationsDelete($rejected->id)->getStatusCode());
        $this->assertSame(404, $controller->leaveApplicationsDelete(9999)->getStatusCode());
    }

    // ---- leave plans: created on first filing, and the balance rule -----------------------------------------------

    private function plans(): \App\Services\Leave\LeavePlanService
    {
        return app(\App\Services\Leave\LeavePlanService::class);
    }

    private function filing(int $staffId, string $start, int $days, int $typeId = 2): Request
    {
        return Request::create('/', 'POST', [
            'staff_id' => $staffId,
            'leave_type_id' => $typeId,
            'available_days' => 99,
            'leave_duration_type' => 'full_day',
            'start_date' => $start,
            'requested_days' => $days,
        ]);
    }

    private function file(Request $request): \Illuminate\Http\JsonResponse
    {
        return app(LeaveManagementController::class)->leaveApplicationsStore($request);
    }

    public function test_the_annual_plan_is_created_on_first_filing_from_the_hire_date_and_only_once(): void
    {
        DB::table('sys_staff')->where('id', 80)->update(['hire_date' => '2026-09-01']); // joined in September
        $staff = \App\Models\Staff::find(80);
        $normal = \App\Models\LeaveType::find(2);

        $first = $this->plans()->ensurePlan($staff, $normal, 2026, 1080);
        $second = $this->plans()->ensurePlan($staff, $normal, 2026, 1080);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['plan']->id, $second['plan']->id);
        $this->assertSame(1, LeaveCalendarPlan::where('employee_id', 80)->count());
        // September to December is 4 months at 1.75 days
        $this->assertEquals(7, $first['plan']->entitled_days);
        $this->assertEquals(7, $first['plan']->balance);
        $this->assertSame(5, (int) $first['plan']->current_status_id);
    }

    public function test_an_existing_plan_is_used_as_it_is_and_never_recalculated(): void
    {
        // Staff 10 has a 21 day plan; a recent hire date would give far fewer days if it were recalculated
        DB::table('sys_staff')->where('id', 10)->update(['hire_date' => '2026-09-01']);

        $result = $this->plans()->ensurePlan(\App\Models\Staff::find(10), \App\Models\LeaveType::find(2), 2026, 1010);

        $this->assertFalse($result['created']);
        $this->assertEquals(21, $result['plan']->entitled_days);
        $this->assertSame(1, LeaveCalendarPlan::where('employee_id', 10)->count());
    }

    public function test_annual_leave_without_a_plan_or_a_hire_date_is_refused_and_nothing_is_created(): void
    {
        $result = $this->plans()->ensurePlan(\App\Models\Staff::find(80), \App\Models\LeaveType::find(2), 2026, 1080);

        $this->assertNull($result['plan']);
        $this->assertStringContainsString('hire date', $result['error']);
        $this->assertSame(0, LeaveCalendarPlan::where('employee_id', 80)->count());
    }

    public function test_a_plan_that_is_not_approved_cannot_be_used(): void
    {
        DB::table('sys_hr_leave_calendar')->where('employee_id', 11)->update(['current_status_id' => 1]);

        $result = $this->plans()->ensurePlan(\App\Models\Staff::find(11), \App\Models\LeaveType::find(2), 2026, 1011);

        $this->assertNull($result['plan']);
        $this->assertStringContainsString('not approved', $result['error']);
    }

    public function test_filing_annual_leave_creates_the_missing_plan_and_measures_against_it(): void
    {
        DB::table('sys_staff')->where('id', 80)->update(['hire_date' => '2026-09-01']);

        $this->actingAs($this->user(80));
        $response = $this->file($this->filing(80, '2026-10-05', 3));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, LeaveCalendarPlan::where('employee_id', 80)->count());
        $request = LeaveRequest::latest('id')->first();
        $this->assertEquals(7, $request->entitled_days);
        $this->assertEquals(4, $request->days_balance);
    }

    public function test_filing_annual_leave_with_no_plan_and_no_hire_date_is_a_422(): void
    {
        $this->actingAs($this->user(80));
        $response = $this->file($this->filing(80, '2026-10-05', 3));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('hire date', json_decode($response->getContent(), true)['error']);
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_a_request_for_more_days_than_available_is_refused_for_staff_but_warned_for_hr(): void
    {
        $this->actingAs($this->user(10)); // a field officer with a 21 day plan
        $refused = $this->file($this->filing(10, '2026-10-05', 30));
        $this->assertSame(422, $refused->getStatusCode());
        $this->assertStringContainsString('only 21 are available', json_decode($refused->getContent(), true)['error']);
        $this->assertSame(0, LeaveRequest::count());

        $this->actingAs($this->user(80)); // a director may file for others and go over, with a warning
        $allowed = $this->file($this->filing(10, '2026-10-05', 30));
        $this->assertSame(200, $allowed->getStatusCode());
        $this->assertStringContainsString('only 21 are available', json_decode($allowed->getContent(), true)['warning']);
        // The request records what its dates really charge (a Saturday is half a day), so the balance is the 21 days less that
        $saved = LeaveRequest::latest('id')->first();
        $this->assertEquals(21 - $saved->requested_days, $saved->days_balance);
        $this->assertLessThan(0, $saved->days_balance);
    }

    public function test_days_in_requests_still_waiting_count_against_the_balance(): void
    {
        $this->actingAs($this->user(10));

        $this->assertSame(200, $this->file($this->filing(10, '2026-10-05', 15))->getStatusCode());
        $second = $this->file($this->filing(10, '2026-11-02', 10)); // 21 - 15 = 6 left

        $this->assertSame(422, $second->getStatusCode());
        $this->assertStringContainsString('only 6 are available', json_decode($second->getContent(), true)['error']);
    }

    public function test_sick_leave_with_pay_tiers_can_run_past_its_entitlement(): void
    {
        DB::table('sys_hr_leave_types')->insert(['id' => 1, 'type_name' => 'Sick Leave', 'entitled_days' => 7]);
        DB::table('sys_hr_leave_type_tiers')->insert([
            ['leave_type_id' => 1, 'tier_order' => 1, 'label' => 'Full pay', 'days' => 7, 'pay_rate' => 100],
            ['leave_type_id' => 1, 'tier_order' => 2, 'label' => 'Half pay', 'days' => 7, 'pay_rate' => 50],
            ['leave_type_id' => 1, 'tier_order' => 3, 'label' => 'No pay', 'days' => null, 'pay_rate' => 0],
        ]);

        $this->actingAs($this->user(10));
        $response = $this->file($this->filing(10, '2026-10-05', 12, 1));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_other_leave_types_without_tiers_are_measured_against_the_leave_types_days(): void
    {
        DB::table('sys_hr_leave_types')->insert(['id' => 3, 'type_name' => 'Compassionate Leave', 'entitled_days' => 5]);

        $this->actingAs($this->user(10));
        $response = $this->file($this->filing(10, '2026-10-05', 6, 3));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('only 5 are available', json_decode($response->getContent(), true)['error']);
        $this->assertSame(0, LeaveCalendarPlan::where('leave_type_id', 3)->count()); // only annual leave is planned
    }

    public function test_editing_a_pending_request_finds_the_plan_like_filing_does(): void
    {
        $this->actingAs($this->user(10));
        $this->file($this->filing(10, '2026-10-05', 3));
        $request = LeaveRequest::latest('id')->first();

        // The plan starts on 1 January: editing used to demand a plan starting on the leave's own start date
        $response = app(LeaveManagementController::class)->leaveApplicationsUpdate($this->filing(10, '2026-10-12', 4), $request->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals(4, $request->fresh()->requested_days);
    }

    public function test_the_available_days_shown_on_the_form_match_the_rule(): void
    {
        \Carbon\Carbon::setTestNow('2026-10-25');
        $this->actingAs($this->user(10));
        $this->file($this->filing(10, '2026-10-05', 5)); // 5 days waiting
        $controller = app(LeaveManagementController::class);

        $mine = json_decode($controller->availableLeaveDays(10, 2)->getContent(), true);
        $this->assertTrue($mine['success']);
        $this->assertEquals(16, $mine['available_days']); // 21 less the 5 waiting

        $this->assertSame(403, $controller->availableLeaveDays(11, 2)->getStatusCode()); // someone else's balance

        $this->actingAs($this->user(80));
        $noHireDate = json_decode($controller->availableLeaveDays(80, 2)->getContent(), true);
        $this->assertFalse($noHireDate['success']);
        $this->assertStringContainsString('hire date', $noHireDate['error']);
        \Carbon\Carbon::setTestNow();
    }

    public function test_initialising_a_year_with_existing_plans_only_fills_the_gaps(): void
    {
        $this->actingAs($this->user(80));

        $response = app(LeaveManagementController::class)->generateAnnualPlans(Request::create('/', 'POST', ['year' => 2026]));
        $body = json_decode($response->getContent(), true);

        $this->assertTrue($body['success']);
        $this->assertStringContainsString('left untouched', $body['message']);
        $this->assertStringContainsString('no hire date', $body['message']);
        Bus::assertDispatched(\App\Jobs\GenerateAnnualLeaveCalendarPlans::class);
    }

    // ---- follow-up fixes -------------------------------------------------------------------------------------------

    public function test_plans_hr_creates_or_edits_by_hand_are_approved_straight_away(): void
    {
        $this->actingAs($this->user(80));
        $controller = app(LeaveManagementController::class);

        $created = $controller->leaveCalendarPlanStore(Request::create('/', 'POST', [
            'employee_id' => 80,
            'leave_type_id' => 2,
            'start_date' => '2027-01-01',
            'entitled_days' => 21,
        ]));

        $this->assertSame(200, $created->getStatusCode());
        $plan = LeaveCalendarPlan::where('employee_id', 80)->first();
        $this->assertSame(5, (int) $plan->current_status_id);

        DB::table('sys_hr_leave_calendar')->where('id', $plan->id)->update(['current_status_id' => 1]);
        $controller->leaveCalendarPlanUpdate(Request::create('/', 'POST', [
            'employee_id' => 80,
            'leave_type_id' => 2,
            'start_date' => '2027-01-01',
            'entitled_days' => 18,
        ]), $plan->id);

        $this->assertSame(5, (int) $plan->fresh()->current_status_id);
        $this->assertEquals(18, $plan->fresh()->entitled_days);
    }

    public function test_approving_other_leave_types_does_not_touch_an_old_plan_for_that_type(): void
    {
        DB::table('sys_hr_leave_types')->insert(['id' => 1, 'type_name' => 'Sick Leave', 'entitled_days' => 7]);
        DB::table('sys_hr_leave_calendar')->insert(['employee_id' => 10, 'leave_type_id' => 1, 'start_date' => '2026-01-01 00:00:00', 'entitled_days' => 7, 'balance' => 7, 'current_status_id' => 5]);
        $sick = LeaveRequest::create(['staff_id' => 10, 'leave_type_id' => 1, 'start_date' => '2026-10-19 08:00:00', 'end_date' => '2026-10-21 17:00:00', 'current_status_id' => 24, 'status_changed_by' => 1020]);

        $result = $this->service()->approve($sick, $this->user(60));

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $sick->fresh()->used_days);
        $this->assertEquals(7, LeaveCalendarPlan::where('employee_id', 10)->where('leave_type_id', 1)->value('balance'));
        $this->assertEquals(21, LeaveCalendarPlan::where('employee_id', 10)->where('leave_type_id', 2)->value('balance'));
    }

    public function test_it_says_who_a_pending_request_is_really_waiting_on(): void
    {
        $router = app(LeaveApprovalRouter::class);

        $this->assertSame('supervisor', $router->waitingOn($this->leave(10)));
        $this->assertSame('hr', $router->waitingOn($this->leave(50)));        // no position: only HR can act
        $this->assertSame('hr', $router->waitingOn($this->leave(10, 24)));
        $this->assertNull($router->waitingOn($this->leave(10, 5)));
        $this->assertNull($router->waitingOn($this->leave(10, 41)));
    }

    public function test_the_leave_list_carries_who_each_pending_request_waits_on(): void
    {
        $waitingOnSupervisor = $this->leave(10);
        $waitingOnHr = $this->leave(50);

        $this->actingAs($this->user(80));
        $rows = json_decode(app(LeaveManagementController::class)->leaveApplicationsList()->getContent(), true)['leave_applications'];
        $byId = array_column($rows, 'waiting_on', 'id');

        $this->assertSame('supervisor', $byId[$waitingOnSupervisor->id]);
        $this->assertSame('hr', $byId[$waitingOnHr->id]);
    }

    public function test_not_allowed_is_a_403_and_an_already_actioned_request_is_a_422(): void
    {
        $this->assertSame(403, $this->service()->approve($this->leave(10), $this->user(30))['status']);
        $this->assertSame(403, $this->service()->reject($this->leave(10), $this->user(30))['status']);

        $approved = $this->leave(10, 5);
        $this->assertSame(422, $this->service()->approve($approved, $this->user(60))['status']);
    }

    public function test_approving_or_rejecting_a_record_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->user(60));
        $controller = app(WorkflowApprovalController::class);

        $approve = $controller->approve(Request::create('/', 'POST', ['section' => 'Leave Application']), 9999);
        $reject = $controller->reject(Request::create('/', 'POST', ['section' => 'Leave Application']), 9999);

        $this->assertSame(404, $approve->getStatusCode());
        $this->assertSame(404, $reject->getStatusCode());
    }
}
