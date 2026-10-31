<?php

namespace Tests\Feature;

use App\Http\Controllers\StaffController;
use App\Models\Contract;
use App\Models\User;
use App\Services\EmployeeContractExpiry;
use App\Services\EmployeeContractReport;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeContractExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Model::unsetEventDispatcher();
        $this->clock('2026-10-29 09:00:00');
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id(); $t->string('full_names'); $t->integer('current_status_id');
            $t->string('staff_number')->nullable(); $t->integer('department_id')->nullable();
        });
        Schema::create('sys_set_departments', function (Blueprint $t) { $t->id(); $t->string('department_name'); });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) { $t->id(); $t->string('position_name'); });
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id(); $t->integer('staff_id'); $t->dateTime('date_start')->nullable(); $t->dateTime('date_end')->nullable();
            $t->integer('current_status_id'); $t->integer('position_id')->nullable(); $t->integer('created_by')->nullable();
            $t->dateTime('date_time')->nullable(); $t->decimal('basic_salary')->nullable(); $t->string('staff_number')->nullable();
        });
        Schema::create('sys_hr_employee_contracts_279_repeat', function (Blueprint $t) { $t->id(); $t->integer('parent_id'); });
        Schema::create('josya_users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('block')->default(0); });
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('cb_role_user', function (Blueprint $t) {
            $t->integer('user_id'); $t->integer('role_id'); $t->string('user_type');
        });
        (require database_path('migrations/2026_01_23_115917_create_notifications_table.php'))->up();
        DB::table('sys_set_departments')->insert(['id'=>1, 'department_name'=>'Operations']);
        DB::table('sys_staff')->insert(['id'=>1, 'full_names'=>'Test Employee', 'staff_number'=>'PER-001', 'department_id'=>1, 'current_status_id'=>1]);
        DB::table('josya_users')->insert(['id'=>1, 'name'=>'Test HR', 'block'=>0]);
        DB::table('cb_roles')->insert(['id'=>1, 'name'=>'hr_manager']);
        DB::table('cb_role_user')->insert(['user_id'=>1, 'role_id'=>1, 'user_type'=>User::class]);
    }

    private function clock(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date, 'Africa/Nairobi'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($date, 'Africa/Nairobi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function contract(?string $end, ?string $start = '2026-01-01', int $status = 5, int $staffId = 1): Contract
    {
        return Contract::create(['staff_id'=>$staffId, 'date_start'=>$start, 'date_end'=>$end, 'current_status_id'=>$status]);
    }

    private function addStaff(int $id, int $status = 1): void
    {
        DB::table('sys_staff')->insert(['id'=>$id, 'full_names'=>"Test Employee {$id}", 'current_status_id'=>$status]);
    }

    public function test_tracking_labels_separate_dates_from_approval(): void
    {
        $expiry = app(EmployeeContractExpiry::class);
        foreach ([
            ['2026-11-29','2026-01-01','active',31],
            ['2026-11-28','2026-01-01','expiring_soon',30],
            ['2026-10-29','2026-01-01','expiring_soon',0],
            ['2026-10-28','2026-01-01','expired',-1],
            ['2026-12-01','2026-11-01','upcoming',33],
            [null,'2026-01-01','missing_dates',null],
            ['2026-11-01',null,'missing_dates',null],
        ] as [$end,$start,$status,$days]) {
            $tracking=$expiry->tracking(new Contract(['date_start'=>$start,'date_end'=>$end]));
            $this->assertSame($status,$tracking['status']);
            $this->assertSame($days,$tracking['days_remaining']);
        }
    }

    public function test_report_boundaries_separate_upcoming_from_expired(): void
    {
        foreach ([0,7,8,14,15,30,31,-1,-30,-31] as $index=>$days) {
            $id=$index+1;
            if ($id>1) $this->addStaff($id);
            $this->contract(CarbonImmutable::today()->addDays($days)->toDateString(), staffId:$id);
        }
        $summary=app(EmployeeContractReport::class)->summary();
        $this->assertSame(2,$summary['due_7']);
        $this->assertSame(4,$summary['due_14']);
        $this->assertSame(6,$summary['due_30']);
        $this->assertSame(3,$summary['expired']);
        $this->assertSame(2,$summary['expired_30']);
        $this->assertSame('2026-10-29',$summary['as_of']);
        $this->assertSame('2026-11-28',$summary['through']);
    }

    public function test_many_contracts_create_only_one_summary_per_recipient(): void
    {
        $this->contract('2026-11-05');
        $this->addStaff(2); $this->contract('2026-11-10',staffId:2);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(1,DB::table('notifications')->count());
        $notification=DB::table('notifications')->first();
        $this->assertSame('EmployeeContractExpirySummary',$notification->type);
        $this->assertStringContainsString('2 employee contracts expire within 30 days',$notification->message);
        $this->assertStringContainsString('window=30',$notification->action_url);
        $this->assertSame(2,json_decode($notification->data,true)['due_30']);
        $this->assertSame(5,Contract::first()->current_status_id);
    }

    public function test_repeated_runs_update_the_same_unread_summary_and_keep_count_fresh(): void
    {
        $this->contract('2026-11-05');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $id=DB::table('notifications')->value('id');
        $this->addStaff(2); $this->contract('2026-11-11',staffId:2);
        $this->clock('2026-10-30 09:00:00');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(1,DB::table('notifications')->count());
        $this->assertSame($id,DB::table('notifications')->value('id'));
        $this->assertSame(2,json_decode(DB::table('notifications')->value('data'),true)['due_30']);
        $this->assertNull(DB::table('notifications')->value('read_at'));
    }

    public function test_read_summary_is_not_repeated_same_day_but_next_day_gets_one(): void
    {
        $this->contract('2026-11-05');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        DB::table('notifications')->update(['read_at'=>now()]);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(1,DB::table('notifications')->count());
        $this->clock('2026-10-30 09:00:00');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(2,DB::table('notifications')->count());
        $this->assertSame(1,DB::table('notifications')->whereNull('read_at')->count());
    }

    public function test_zero_upcoming_contracts_resolve_unread_summary_without_alerting_for_history(): void
    {
        $contract=$this->contract('2026-11-05');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $contract->update(['date_end'=>'2020-01-01','date_start'=>'2019-01-01']);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(1,DB::table('notifications')->count());
        $this->assertNotNull(DB::table('notifications')->value('read_at'));
        $this->assertSame(1,app(EmployeeContractReport::class)->summary()['expired']);
    }

    public function test_reading_an_older_updated_summary_does_not_create_another_that_day(): void
    {
        $this->contract('2026-11-05');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->clock('2026-10-30 09:00:00');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        DB::table('notifications')->update(['read_at'=>now()]);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(1,DB::table('notifications')->count());
    }

    public function test_dry_run_writes_nothing_even_when_a_summary_would_change(): void
    {
        $this->contract('2026-11-05');
        $this->artisan('hr:check-expiring-contracts',['--dry-run'=>true])->expectsOutput('No changes made.')->assertSuccessful();
        $this->assertSame(0,DB::table('notifications')->count());
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $before=DB::table('notifications')->get()->toJson();
        $this->addStaff(2); $this->contract('2026-11-05',staffId:2);
        $this->artisan('hr:check-expiring-contracts',['--dry-run'=>true])->assertSuccessful();
        $this->assertSame($before,DB::table('notifications')->get()->toJson());
    }

    public function test_invalid_draft_future_and_inactive_contracts_are_excluded(): void
    {
        $this->contract('2026-11-05',status:1);
        $this->contract(null);
        $this->contract('2026-01-01','2026-10-01');
        $this->contract('2026-11-05','2026-11-01');
        $this->addStaff(2,2); $this->contract('2026-11-05',staffId:2);
        $this->assertSame(0,app(EmployeeContractReport::class)->summary()['due_30']);
    }

    public function test_started_renewal_suppresses_old_contract_and_future_renewal_does_not(): void
    {
        $old=$this->contract('2026-11-05');
        $renewal=$this->contract('2027-10-28','2026-10-29');
        $this->assertFalse(app(EmployeeContractReport::class)->currentContracts()->whereKey($old->id)->exists());
        $renewal->update(['date_start'=>'2026-11-06']);
        $this->assertSame(1,app(EmployeeContractReport::class)->summary()['due_30']);
    }

    public function test_on_leave_employees_are_included(): void
    {
        DB::table('sys_staff')->update(['current_status_id'=>4]);
        $this->contract('2026-11-05');
        $this->assertSame(1,app(EmployeeContractReport::class)->summary()['due_7']);
    }

    public function test_directors_and_hr_receive_one_copy_each_and_blocked_users_receive_none(): void
    {
        $this->contract('2026-11-05');
        DB::table('cb_roles')->insert(['id'=>2,'name'=>'directors']);
        DB::table('josya_users')->insert([['id'=>2,'name'=>'Director','block'=>0],['id'=>3,'name'=>'Blocked Director','block'=>1]]);
        DB::table('cb_role_user')->insert([
            ['user_id'=>2,'role_id'=>2,'user_type'=>User::class],
            ['user_id'=>3,'role_id'=>2,'user_type'=>User::class],
            ['user_id'=>1,'role_id'=>2,'user_type'=>User::class],
        ]);
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame([1,2],DB::table('notifications')->orderBy('notifiable_id')->pluck('notifiable_id')->all());
    }

    public function test_legacy_individual_history_is_preserved_but_marked_read(): void
    {
        DB::table('notifications')->insert(['type'=>'EmployeeContractExpiry','notifiable_type'=>User::class,'notifiable_id'=>1,'data'=>'{}','title'=>'Old individual alert','created_at'=>now(),'updated_at'=>now()]);
        $this->contract('2026-11-05');
        $this->artisan('hr:check-expiring-contracts')->assertSuccessful();
        $this->assertSame(2,DB::table('notifications')->count());
        $this->assertNotNull(DB::table('notifications')->where('type','EmployeeContractExpiry')->value('read_at'));
    }

    public function test_contract_api_includes_department_approval_and_live_summary(): void
    {
        $contract=$this->contract('2026-11-05');
        $user=\Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturn(true);
        $this->actingAs($user);
        $response=app(StaffController::class)->contractList();
        $this->assertSame(200,$response->getStatusCode());
        $payload=$response->getData(true);
        $this->assertSame('Operations',$payload['contracts'][0]['department']);
        $this->assertSame('PER-001',$payload['contracts'][0]['staff_number']);
        $this->assertSame('Approved',$payload['contracts'][0]['approval_status']);
        $this->assertTrue($payload['contracts'][0]['is_current_approved']);
        $this->assertSame(1,$payload['report']['due_7']);
        $this->assertArrayHasKey('generated_at',$payload['report']);
    }

    public function test_daily_summary_is_registered_at_eight_am_nairobi(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $events=collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn($event)=>str_contains($event->command ?? '', 'hr:check-expiring-contracts'));
        $this->assertCount(1,$events);
        $this->assertSame('0 8 * * *',$events->first()->expression);
        $this->assertSame('Africa/Nairobi',$events->first()->timezone);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    public function test_dashboard_returns_next_five_in_expiry_order_with_the_same_report_counts(): void
    {
        $this->contract('2026-10-30');
        foreach ([2,3,4,5,6,7] as $id) {
            $this->addStaff($id);
            $this->contract(CarbonImmutable::today()->addDays($id)->toDateString(), staffId:$id);
        }
        $this->addStaff(8); $this->contract('2026-10-28', staffId:8);
        $report=app(EmployeeContractReport::class);
        $dashboard=$report->dashboard();
        $this->assertSame($report->summary(),$dashboard['summary']);
        $this->assertSame(7,$dashboard['summary']['due_30']);
        $this->assertSame(1,$dashboard['summary']['expired']);
        $this->assertCount(5,$dashboard['upcoming']);
        $this->assertSame([1,2,3,4,5],$dashboard['upcoming']->pluck('days_remaining')->all());
        $this->assertSame('Operations',$dashboard['upcoming'][0]['department']);
        $this->assertSame('PER-001',$dashboard['upcoming'][0]['staff_number']);
        $this->assertStringContainsString('contract_id=',$dashboard['upcoming'][0]['report_url']);
        $this->assertArrayNotHasKey('basic_salary',$dashboard['upcoming'][0]);
        $this->assertSame(0,DB::table('notifications')->count());
    }

    public function test_dashboard_has_a_clear_empty_result_for_no_upcoming_contracts(): void
    {
        $this->contract('2026-10-28');
        $data=app(EmployeeContractReport::class)->dashboard();
        $this->assertSame(0,$data['summary']['due_30']);
        $this->assertCount(0,$data['upcoming']);
        $this->assertSame(1,$data['summary']['expired']);
    }

    public function test_dashboard_endpoint_restricts_access_and_does_not_cache_live_figures(): void
    {
        $user=\Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('hr_manager')->andReturn(false);
        $user->shouldReceive('hasRole')->with('directors')->andReturn(true);
        $this->actingAs($user)->withoutMiddleware();
        $this->contract('2026-11-05');
        $response=$this->getJson(route('dashboard.contract-expiry-overview'))
            ->assertOk()->assertJsonPath('summary.due_7',1)->assertJsonCount(1,'upcoming');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
    }

    public function test_other_roles_cannot_read_the_contract_dashboard_endpoint(): void
    {
        $user=\Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(false);
        $this->actingAs($user)->withoutMiddleware();
        $this->getJson(route('dashboard.contract-expiry-overview'))->assertForbidden();
    }

    public function test_dashboard_partial_renders_filtered_report_links(): void
    {
        $this->view('dashboards.contract-expiry-overview')->assertSee('Contracts expiring within 30 days')
            ->assertDontSee('window=7',false)->assertDontSee('window=14',false)->assertSee('window=30',false)
            ->assertDontSee('window=expired',false)->assertSee('Next five contracts');
    }
}
