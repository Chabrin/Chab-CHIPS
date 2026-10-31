<?php

namespace Tests\Feature;

use App\Models\LeaveCalendarPlan;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDaysTaken;
use App\Models\LeaveType;
use App\Models\Staff;
use App\Models\User;
use App\Services\Leave\LeaveOverviewService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeaveOverviewTest extends TestCase
{
    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Model::unsetEventDispatcher();
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id(); $t->string('full_names'); $t->string('staff_number')->nullable(); $t->string('hire_date')->nullable(); $t->integer('current_status_id');
        });
        Schema::create('sys_hr_leave_types', function (Blueprint $t) {
            $t->id(); $t->string('type_name'); $t->integer('current_status_id'); $t->decimal('entitled_days')->nullable();
            $t->boolean('counts_weekends')->default(false); $t->boolean('counts_public_holidays')->default(false);
        });
        Schema::create('sys_hr_leave_calendar', function (Blueprint $t) {
            $t->id(); $t->integer('employee_id'); $t->integer('leave_type_id'); $t->date('start_date'); $t->date('end_date');
            $t->decimal('entitled_days')->nullable(); $t->decimal('balance')->nullable(); $t->integer('current_status_id');
        });
        Schema::create('sys_hr_leave_requests', function (Blueprint $t) {
            $t->id(); $t->integer('staff_id'); $t->integer('leave_type_id'); $t->dateTime('start_date'); $t->dateTime('end_date');
            $t->decimal('days')->default(0); $t->decimal('requested_days')->default(0); $t->string('used_days')->nullable(); $t->integer('current_status_id');
        });
        Schema::create('sys_hr_leave_requests_234_repeat', function (Blueprint $t) {
            $t->id(); $t->integer('parent_id'); $t->dateTime('leave_date'); $t->integer('current_status_id');
            $t->decimal('day_fraction')->nullable(); $t->decimal('pay_rate')->nullable(); $t->integer('tier_order')->nullable();
        });
        // Match the calendar's actual table names rather than mocking the counting rules.
        $holiday = new \App\Models\PublicHoliday();
        Schema::create($holiday->getTable(), function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->dateTime('start_date'); $t->dateTime('end_date')->nullable(); $t->string('recurrent'); $t->integer('current_status_id');
        });
        Schema::create((new \App\Models\WorkDay())->getTable(), function (Blueprint $t) {
            $t->id(); $t->integer('week_day'); $t->integer('work_type_id'); $t->integer('current_status_id');
        });
        $this->staff = Staff::create(['full_names' => 'Test Employee', 'staff_number' => 'TEST001', 'hire_date' => '2020-01-01', 'current_status_id' => 1]);
        LeaveType::create(['id' => 1, 'type_name' => 'Sick Leave', 'entitled_days' => 14, 'current_status_id' => 5]);
    }

    private function request(array $changes = []): LeaveRequest
    {
        return LeaveRequest::create($changes + ['staff_id' => $this->staff->id, 'leave_type_id' => 1, 'start_date' => '2026-10-23 08:00:00', 'end_date' => '2026-10-24 17:00:00', 'days' => 1.5, 'used_days' => '1.5', 'requested_days' => 1.5, 'current_status_id' => 5]);
    }

    private function day(LeaveRequest $request, string $date, float $fraction, int $status = 5): void
    {
        LeaveRequestDaysTaken::create(['parent_id' => $request->id, 'leave_date' => $date, 'day_fraction' => $fraction, 'current_status_id' => $status, 'pay_rate' => 100, 'tier_order' => 1]);
    }

    private function overview(): array
    {
        return app(LeaveOverviewService::class)->forEmployee($this->staff, 2026);
    }

    public function test_daily_fractions_reconcile_to_balances_and_pending_reservations(): void
    {
        $request = $this->request();
        $this->day($request, '2026-10-23', 1);
        $this->day($request, '2026-10-24', 0.5);
        $this->request(['start_date' => '2026-10-26 08:00:00', 'end_date' => '2026-10-26 17:00:00', 'days' => 0, 'requested_days' => 1, 'current_status_id' => 24]);
        $data = $this->overview();
        $category = $data['categories'][0];
        $this->assertSame(1.5, $category['approved']);
        $this->assertSame(1.0, $category['pending']);
        $this->assertSame(12.5, $category['balance']);
        $this->assertSame(11.5, $category['available']);
        $this->assertSame([], $data['warnings']);
        $this->assertSame(0.5, $data['days'][1]['days']);
        $this->assertSame('Pending — preview', $data['days'][2]['status']);
    }

    public function test_refunded_dates_charge_zero_and_rejected_requests_are_excluded(): void
    {
        $request = $this->request(['days' => 1, 'used_days' => '1']);
        $this->day($request, '2026-10-23', 1);
        $this->day($request, '2026-10-24', 0.5, 41);
        $this->request(['current_status_id' => 41]);
        $data = $this->overview();
        $this->assertSame(1.0, $data['categories'][0]['approved']);
        $this->assertCount(2, $data['days']);
        $this->assertSame(0.0, $data['days'][1]['days']);
        $this->assertSame('Refunded / cancelled', $data['days'][1]['status']);
    }

    public function test_legacy_missing_day_records_are_flagged_and_not_assigned_invented_dates(): void
    {
        $this->request();
        $data = $this->overview();
        $this->assertNotEmpty($data['warnings']);
        $this->assertNull($data['days'][0]['date']);
        $this->assertSame(1.5, $data['categories'][0]['approved']);
    }

    public function test_cross_year_daily_allocations_use_the_actual_leave_date_and_flag_source_mismatch(): void
    {
        $request = $this->request(['start_date' => '2025-12-31', 'end_date' => '2026-01-02', 'days' => 3]);
        $this->day($request, '2025-12-31', 1);
        $this->day($request, '2026-01-01', 1);
        $this->day($request, '2026-01-02', 1);
        $data = $this->overview();
        $this->assertSame(2.0, $data['categories'][0]['approved']);
        $this->assertCount(2, $data['days']);
        $this->assertNotEmpty($data['warnings']);
    }

    public function test_annual_plan_balance_is_preserved_and_discrepancies_are_visible(): void
    {
        DB::table('sys_hr_leave_types')->where('id', 1)->update(['type_name' => 'Annual Leave']);
        LeaveCalendarPlan::create(['employee_id' => $this->staff->id, 'leave_type_id' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'entitled_days' => 21, 'balance' => 17, 'current_status_id' => 5]);
        $data = $this->overview();
        $this->assertSame(17.0, $data['categories'][0]['balance']);
        $this->assertNotEmpty($data['warnings']);
        $this->assertSame(17.0, (float) DB::table('sys_hr_leave_calendar')->value('balance'));
    }

    public function test_missing_hire_date_reports_unavailable_annual_balance_without_creating_a_plan(): void
    {
        DB::table('sys_hr_leave_types')->where('id', 1)->update(['type_name' => 'Annual Leave']);
        $this->staff->update(['hire_date' => null]);
        $data = $this->overview();
        $this->assertNull($data['categories'][0]['entitled']);
        $this->assertNull($data['categories'][0]['available']);
        $this->assertNotEmpty($data['warnings']);
        $this->assertSame(0, DB::table('sys_hr_leave_calendar')->count());
    }

    public function test_pending_preview_respects_holidays_and_half_day_saturdays(): void
    {
        $this->request(['start_date' => '2026-10-23', 'end_date' => '2026-10-25', 'requested_days' => 0.5, 'current_status_id' => 1]);
        DB::table((new \App\Models\PublicHoliday())->getTable())->insert(['title' => 'Test holiday', 'start_date' => '2026-10-23', 'recurrent' => '2', 'current_status_id' => 5]);
        $data = $this->overview();
        $this->assertCount(1, $data['days']);
        $this->assertSame('2026-10-24', $data['days'][0]['date']);
        $this->assertSame(0.5, $data['days'][0]['days']);
    }

    public function test_endpoint_is_limited_to_hr_and_directors_and_excludes_inactive_staff(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(false);
        $this->actingAs($user)->withoutMiddleware();
        $this->getJson(route('dashboard.leave-overview'))->assertForbidden();
    }

    public function test_director_can_read_employee_options_and_invalid_filters_are_rejected(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('hr_manager')->andReturn(false);
        $user->shouldReceive('hasRole')->with('directors')->andReturn(true);
        $this->actingAs($user)->withoutMiddleware();
        $this->getJson(route('dashboard.leave-overview'))->assertOk()->assertJsonCount(1, 'employees');
        $this->getJson(route('dashboard.leave-overview', ['staff_id' => $this->staff->id, 'year' => 2026]))->assertOk()->assertJsonPath('categories.0.name', 'Sick Leave');
        $this->getJson(route('dashboard.leave-overview', ['year' => 1800]))->assertUnprocessable();
        $this->staff->update(['current_status_id' => 2]);
        $this->getJson(route('dashboard.leave-overview', ['staff_id' => $this->staff->id]))->assertNotFound();
    }

    public function test_dashboard_partial_renders_the_employee_filters_and_both_breakdowns(): void
    {
        $this->view('dashboards.leave-overview')->assertSee('Leave overview')->assertSee('Recorded balance')
            ->assertSee('Daily breakdown')->assertSee('lo-employee')->assertSee('lo-year');
    }

    public function test_weekend_counting_categories_include_sunday_and_public_holidays_in_pending_preview(): void
    {
        DB::table('sys_hr_leave_types')->where('id', 1)->update(['counts_weekends' => true, 'counts_public_holidays' => true]);
        $this->request(['start_date' => '2026-10-23', 'end_date' => '2026-10-25', 'requested_days' => 3, 'current_status_id' => 1]);
        DB::table((new \App\Models\PublicHoliday())->getTable())->insert(['title' => 'Test holiday', 'start_date' => '2026-10-23', 'recurrent' => '2', 'current_status_id' => 5]);
        $data = $this->overview();
        $this->assertCount(3, $data['days']);
        $this->assertSame([1.0, 1.0, 1.0], array_column($data['days'], 'days'));
    }

    public function test_same_day_pending_request_preserves_its_requested_fraction(): void
    {
        $this->request(['start_date' => '2026-10-23 08:00:00', 'end_date' => '2026-10-23 10:00:00', 'requested_days' => 0.25, 'current_status_id' => 1]);
        $this->assertSame(0.25, $this->overview()['days'][0]['days']);
    }

    public function test_missing_category_request_is_visible_and_is_not_reassigned_to_current_annual_leave(): void
    {
        DB::table('sys_hr_leave_types')->insert(['id'=>4,'type_name'=>'Annual leave','entitled_days'=>21,'current_status_id'=>5]);
        foreach ([2,4] as $typeId) {
            LeaveCalendarPlan::create(['employee_id'=>$this->staff->id,'leave_type_id'=>$typeId,'start_date'=>'2026-01-01','end_date'=>'2026-12-31','entitled_days'=>21,'balance'=>21,'current_status_id'=>5]);
        }
        $request=$this->request(['leave_type_id'=>2,'start_date'=>'2026-10-02 08:00:00','end_date'=>'2026-10-02 17:00:00','requested_days'=>1,'current_status_id'=>1]);
        $plansBefore=DB::table('sys_hr_leave_calendar')->orderBy('id')->get()->toJson();
        $data=$this->overview();
        $categories=collect($data['categories'])->keyBy('id');
        $this->assertSame('Unconfigured leave category #2',$categories[2]['name']);
        $this->assertSame('missing',$categories[2]['setup_status']);
        $this->assertSame(1.0,$categories[2]['pending']);
        $this->assertNull($categories[2]['entitled']);
        $this->assertNull($categories[2]['balance']);
        $this->assertNull($categories[2]['available']);
        $this->assertNotEmpty($categories[2]['warnings']);
        $this->assertSame(0.0,$categories[4]['pending']);
        $this->assertSame(21.0,$categories[4]['available']);
        $this->assertSame('2026-10-02',$data['days'][0]['date']);
        $this->assertSame(1.0,$data['days'][0]['days']);
        $this->assertSame(2,$data['days'][0]['category_id']);
        $this->assertStringContainsString('recorded request total',$data['days'][0]['status']);
        $this->assertSame(2,LeaveRequest::find($request->id)->leave_type_id);
        $this->assertSame($plansBefore,DB::table('sys_hr_leave_calendar')->orderBy('id')->get()->toJson());
        $this->assertSame(0,DB::table('sys_hr_leave_types')->where('id',2)->count());
    }

    public function test_inactive_category_approved_days_remain_visible_with_balances_unavailable(): void
    {
        DB::table('sys_hr_leave_types')->where('id',1)->update(['current_status_id'=>41]);
        $request=$this->request();
        $this->day($request,'2026-10-23',1);
        $this->day($request,'2026-10-24',0.5);
        $data=$this->overview();
        $category=$data['categories'][0];
        $this->assertSame('Sick Leave (inactive)',$category['name']);
        $this->assertSame('inactive',$category['setup_status']);
        $this->assertSame(1.5,$category['approved']);
        $this->assertNull($category['balance']);
        $this->assertNull($category['available']);
        $this->assertCount(2,$data['days']);
        $this->assertNotEmpty($data['warnings']);
    }

    public function test_unknown_category_multi_day_request_shows_total_without_inventing_daily_rules(): void
    {
        $this->request(['leave_type_id'=>2,'start_date'=>'2026-10-23','end_date'=>'2026-10-25','requested_days'=>2,'current_status_id'=>1]);
        $data=$this->overview();
        $missing=collect($data['categories'])->firstWhere('id',2);
        $this->assertSame(2.0,$missing['pending']);
        $this->assertCount(1,$data['days']);
        $this->assertNull($data['days'][0]['date']);
        $this->assertSame(2.0,$data['days'][0]['days']);
        $this->assertSame('2026-10-23',$data['days'][0]['request_start']);
        $this->assertSame('2026-10-25',$data['days'][0]['request_end']);
    }

    public function test_unknown_category_cross_year_request_does_not_guess_a_yearly_pending_total(): void
    {
        $this->request(['leave_type_id'=>2,'start_date'=>'2025-12-30','end_date'=>'2026-01-03','requested_days'=>3,'current_status_id'=>1]);
        $data=$this->overview();
        $missing=collect($data['categories'])->firstWhere('id',2);
        $this->assertNull($missing['pending']);
        $this->assertNull($data['days'][0]['days']);
        $this->assertStringContainsString('spans years',$data['days'][0]['status']);
        $this->assertNotEmpty($missing['warnings']);
    }

    public function test_cross_year_approved_total_without_daily_records_is_unavailable_instead_of_zero(): void
    {
        $this->request(['leave_type_id'=>2,'start_date'=>'2025-12-30','end_date'=>'2026-01-03']);
        $data=$this->overview();
        $missing=collect($data['categories'])->firstWhere('id',2);
        $this->assertNull($missing['approved']);
        $this->assertNull($data['days'][0]['days']);
        $this->assertStringContainsString('year allocation unavailable',$data['days'][0]['status']);
    }

    public function test_missing_approved_day_fraction_is_not_reported_as_zero(): void
    {
        $request=$this->request(['leave_type_id'=>2]);
        LeaveRequestDaysTaken::create(['parent_id'=>$request->id,'leave_date'=>'2026-10-23','day_fraction'=>null,'current_status_id'=>5]);
        $data=$this->overview();
        $missing=collect($data['categories'])->firstWhere('id',2);
        $this->assertNull($missing['approved']);
        $this->assertNull($data['days'][0]['days']);
        $this->assertNotEmpty($missing['warnings']);
    }
}
