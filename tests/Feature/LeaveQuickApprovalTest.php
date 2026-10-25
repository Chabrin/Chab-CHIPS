<?php

namespace Tests\Feature;

use App\Jobs\SendApprovalNotification;
use App\Models\ConfirmationLog;
use App\Models\LeaveCalendarPlan;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDaysTaken;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeaveQuickApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->integer('current_status_id')->default(1);
        });
        Schema::create('sys_hr_leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('type_name');
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

        DB::table('sys_staff')->insert(['id' => 7, 'current_status_id' => 1]);
        DB::table('sys_hr_leave_types')->insert(['id' => 2, 'type_name' => 'Normal']);
        DB::table('sys_hr_leave_calendar')->insert([
            'employee_id' => 7,
            'leave_type_id' => 2,
            'start_date' => '2026-01-01 00:00:00',
            'entitled_days' => 21,
            'balance' => 21,
            'current_status_id' => 5,
        ]);
    }

    private function request(string $start, string $end, int $status = 1): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => 7,
            'leave_type_id' => 2,
            'start_date' => $start,
            'end_date' => $end,
            'current_status_id' => $status,
        ]);
    }

    public function test_quick_approve_confirms_days_and_deducts_the_plan_balance(): void
    {
        $request = $this->request('2026-10-19 08:00:00', '2026-10-25 17:00:00');

        $result = app(LeaveApprovalService::class)->quickApprove($request, 99);

        $this->assertTrue($result['success']);
        $this->assertSame(5, $request->fresh()->current_status_id);
        // Mon-Fri is 5 days and the Saturday is half a day; the Sunday is not charged
        $this->assertEquals(5.5, $request->fresh()->used_days);
        $this->assertSame(6, LeaveRequestDaysTaken::where('parent_id', $request->id)->where('current_status_id', 5)->count());
        $this->assertEquals(0.5, LeaveRequestDaysTaken::where('parent_id', $request->id)->where('day_type_id', 2)->value('day_fraction'));
        $this->assertEquals(15.5, LeaveCalendarPlan::first()->balance);
        $this->assertSame(1, ConfirmationLog::where('action', 'approve')->count());
        Bus::assertDispatched(SendApprovalNotification::class);
    }

    public function test_quick_approve_refuses_a_request_that_is_already_approved(): void
    {
        $request = $this->request('2026-10-19 08:00:00', '2026-10-23 17:00:00', 5);

        $result = app(LeaveApprovalService::class)->quickApprove($request, 99);

        $this->assertFalse($result['success']);
        $this->assertEquals(21, LeaveCalendarPlan::first()->balance);
    }

    public function test_quick_approve_charges_hours_for_a_partial_day(): void
    {
        $request = $this->request('2026-10-20 09:00:00', '2026-10-20 13:00:00');

        app(LeaveApprovalService::class)->quickApprove($request, 99);

        $this->assertEquals(0.5, $request->fresh()->used_days);
        $this->assertSame(1, LeaveRequestDaysTaken::where('parent_id', $request->id)->count());
        $this->assertEquals(20.5, LeaveCalendarPlan::first()->balance);
    }

    public function test_quick_approve_refuses_dates_with_no_chargeable_days(): void
    {
        $request = $this->request('2026-10-25 08:00:00', '2026-10-25 17:00:00'); // Sunday

        $result = app(LeaveApprovalService::class)->quickApprove($request, 99);

        $this->assertFalse($result['success']);
        $this->assertSame(1, $request->fresh()->current_status_id);
    }

    public function test_quick_approve_charges_a_saturday_as_half_a_day(): void
    {
        $request = $this->request('2026-10-24 08:00:00', '2026-10-24 17:00:00'); // Saturday

        $result = app(LeaveApprovalService::class)->quickApprove($request, 99);

        $this->assertTrue($result['success']);
        $this->assertEquals(0.5, $request->fresh()->used_days);
        $this->assertEquals(20.5, LeaveCalendarPlan::first()->balance);
    }

    public function test_sick_tiers_count_a_saturday_as_half_a_day(): void
    {
        DB::table('sys_hr_leave_types')->insert(['id' => 1, 'type_name' => 'Sick Leave']);
        DB::table('sys_hr_leave_type_tiers')->insert([
            ['leave_type_id' => 1, 'tier_order' => 1, 'label' => 'Full pay', 'days' => 7, 'pay_rate' => 100],
            ['leave_type_id' => 1, 'tier_order' => 2, 'label' => 'Half pay', 'days' => 7, 'pay_rate' => 50],
            ['leave_type_id' => 1, 'tier_order' => 3, 'label' => 'No pay', 'days' => null, 'pay_rate' => 0],
        ]);
        $sick = LeaveRequest::create(['staff_id' => 7, 'leave_type_id' => 1, 'start_date' => '2026-10-05 08:00:00', 'end_date' => '2026-10-17 17:00:00', 'current_status_id' => 5]);
        // 6 weekdays then a Saturday (half) = 6.5 days used
        foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-12'] as $date) {
            LeaveRequestDaysTaken::create(['parent_id' => $sick->id, 'current_status_id' => 5, 'leave_date' => $date . ' 00:00:00', 'day_fraction' => 1]);
        }
        LeaveRequestDaysTaken::create(['parent_id' => $sick->id, 'current_status_id' => 5, 'leave_date' => '2026-10-17 00:00:00', 'day_fraction' => 0.5]);

        $breakdown = app(\App\Services\Leave\LeaveTierService::class)->breakdown(7, 1, 2026, 2);

        $this->assertEquals(6.5, $breakdown['used_this_year']);
        // 6.5 days used: half a day of full pay is left, the rest of the two days is half pay
        $this->assertSame('0.5 day(s) at Full pay (100%), 1.5 day(s) at Half pay (50%)', $breakdown['summary_text']);
    }

    public function test_a_day_that_straddles_the_full_pay_boundary_is_split_the_same_way_in_preview_and_record(): void
    {
        DB::table('sys_hr_leave_types')->insert(['id' => 1, 'type_name' => 'Sick Leave']);
        DB::table('sys_hr_leave_type_tiers')->insert([
            ['leave_type_id' => 1, 'tier_order' => 1, 'label' => 'Full pay', 'days' => 7, 'pay_rate' => 100],
            ['leave_type_id' => 1, 'tier_order' => 2, 'label' => 'Half pay', 'days' => 7, 'pay_rate' => 50],
            ['leave_type_id' => 1, 'tier_order' => 3, 'label' => 'No pay', 'days' => null, 'pay_rate' => 0],
        ]);
        $sick = LeaveRequest::create(['staff_id' => 7, 'leave_type_id' => 1, 'start_date' => '2026-10-05 08:00:00', 'end_date' => '2026-10-19 17:00:00', 'current_status_id' => 5]);
        // Six full days, then a Saturday (half a day), then a Monday (a full day)
        foreach ([['2026-10-05', 1], ['2026-10-06', 1], ['2026-10-07', 1], ['2026-10-08', 1], ['2026-10-09', 1], ['2026-10-12', 1], ['2026-10-17', 0.5], ['2026-10-19', 1]] as [$date, $fraction]) {
            LeaveRequestDaysTaken::create(['parent_id' => $sick->id, 'current_status_id' => 5, 'leave_date' => $date . ' 00:00:00', 'day_fraction' => $fraction]);
        }

        $tiers = app(\App\Services\Leave\LeaveTierService::class);
        $tiers->retag(7, 1, 2026);

        $rates = LeaveRequestDaysTaken::where('parent_id', $sick->id)->orderBy('leave_date')->pluck('pay_rate')->map(fn($r) => (float) $r)->all();
        // The Saturday finishes the 7 full-pay days; the Monday is half inside them and half outside: 75%
        $this->assertSame([100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 75.0], $rates);

        // The preview for the same two days gives the same split
        DB::table('sys_hr_leave_requests_234_repeat')->where('parent_id', $sick->id)->whereIn('leave_date', ['2026-10-17 00:00:00', '2026-10-19 00:00:00'])->delete();
        $this->assertSame('1 day(s) at Full pay (100%), 0.5 day(s) at Half pay (50%)', $tiers->breakdown(7, 1, 2026, [0.5, 1.0])['summary_text']);
    }

    public function test_hr_working_day_settings_override_the_saturday_default(): void
    {
        // HR marks Saturday as a full working day
        DB::table('sys_hr_working_days')->insert(['week_day' => '6', 'work_type_id' => '1', 'current_status_id' => 5]);
        $request = $this->request('2026-10-24 08:00:00', '2026-10-24 17:00:00');

        app(LeaveApprovalService::class)->quickApprove($request, 99);

        $this->assertEquals(1, $request->fresh()->used_days);
    }

    public function test_quick_approve_refuses_inactive_staff(): void
    {
        DB::table('sys_staff')->where('id', 7)->update(['current_status_id' => 3]);
        $request = $this->request('2026-10-19 08:00:00', '2026-10-23 17:00:00');

        $this->assertFalse(app(LeaveApprovalService::class)->quickApprove($request, 99)['success']);
    }

    public function test_quick_reject_marks_the_request_rejected_without_touching_the_balance(): void
    {
        $request = $this->request('2026-10-19 08:00:00', '2026-10-23 17:00:00');

        $result = app(LeaveApprovalService::class)->quickReject($request, 99);

        $this->assertTrue($result['success']);
        $this->assertSame(41, $request->fresh()->current_status_id);
        $this->assertEquals(21, LeaveCalendarPlan::first()->balance);
    }
}
