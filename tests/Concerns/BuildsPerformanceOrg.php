<?php

namespace Tests\Concerns;

use App\Models\PerformanceCycle;
use App\Models\PerformanceKpi;
use App\Models\PerformanceSupervisorAssignment;
use App\Models\Staff;
use App\Models\User;
use App\Services\Performance\PerformanceCycleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A small company for the Performance Review tests.
 *
 *   Managing Director (pos 1, nobody above)            staff 1 (no login)
 *     Zone Manager (pos 2)                              staff 2 and 3, both with a login
 *       Field Officer (pos 3)                           staff 10, 11, 12 (12 has no login)
 *     Chief Accountant (pos 5)                          staff 20, with a login
 *       Accountant (pos 4)                              staff 21, with a login
 *     Operations & HR Manager (pos 7)                   staff 60, with a login
 *   Lone (pos 6, reports to nobody)                     staff 30
 *   Staff 40 has no position; staff 50 is a Field Officer who has left.
 *
 * Several people hold the Zone Manager position, so a Field Officer's supervisor has to be named; the Chief Accountant is
 * the only holder of theirs, so the Accountant's supervisor is automatic.
 */
trait BuildsPerformanceOrg
{
    protected const POS_MD = 1;

    protected const POS_ZONE_MANAGER = 2;

    protected const POS_FIELD_OFFICER = 3;

    protected const POS_ACCOUNTANT = 4;

    protected const POS_CHIEF_ACCOUNTANT = 5;

    protected const POS_LONE = 6;

    protected const POS_HR = 7;

    protected function buildPerformanceSchema(): void
    {
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('full_names')->nullable();
            $t->integer('position_id')->nullable();
            $t->integer('department_id')->nullable();
            $t->integer('current_status_id')->default(1);
            $t->string('hire_date')->nullable();
            $t->string('employment_type')->nullable();
            $t->string('staff_number')->nullable();
        });
        // What the records beside the ratings are worked out from
        Schema::create('sys_hr_employees_attendance', function (Blueprint $t) {
            $t->id();
            $t->string('employee_id')->nullable();
            $t->string('month_year')->nullable();
        });
        Schema::create('sys_hr_employees_attendance_255_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->dateTime('date');
            $t->string('time_in')->nullable();
            $t->string('time_out')->nullable();
            $t->decimal('hours', 8, 2)->nullable();
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
            $t->decimal('days', 8, 2)->default(0);
            $t->decimal('recalled_days', 8, 2)->nullable();
            $t->dateTime('recall_effective_date')->nullable();
            $t->integer('current_status_id')->default(1);
        });
        Schema::create('sys_hr_public_holidays', function (Blueprint $t) {
            $t->id();
            $t->string('title')->nullable();
            $t->dateTime('start_date');
            $t->dateTime('end_date')->nullable();
            $t->string('recurrent')->nullable();
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('sys_hr_working_days', function (Blueprint $t) {
            $t->id();
            $t->integer('week_day');
            $t->integer('work_type_id');
            $t->integer('current_status_id')->default(5);
        });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) {
            $t->id();
            $t->string('position_name');
            $t->integer('reports_to_id')->nullable();
            $t->integer('department_id')->nullable();
            $t->boolean('is_active')->default(true);
        });
        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->integer('block')->default(0);
            $t->integer('activation')->default(1);
            $t->integer('staff_id')->nullable();
            $t->rememberToken();
        });
        Schema::create('sys_user_staff_links', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->integer('staff_id');
        });

        // The bell, and the roles that make someone HR
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
        if (! Schema::hasTable('cb_roles')) {
            Schema::create('cb_roles', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
            });
        }
        if (! Schema::hasTable('cb_role_user')) {
            Schema::create('cb_role_user', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('role_id');
            });
        }

        (require database_path('migrations/2026_11_12_100000_create_performance_review_tables.php'))->up();
    }

    protected function seedPerformanceOrg(): void
    {
        foreach ([
            [self::POS_MD, 'Managing Director', null],
            [self::POS_ZONE_MANAGER, 'Zone Manager', self::POS_MD],
            [self::POS_FIELD_OFFICER, 'Field Officer', self::POS_ZONE_MANAGER],
            [self::POS_ACCOUNTANT, 'Accountant', self::POS_CHIEF_ACCOUNTANT],
            [self::POS_CHIEF_ACCOUNTANT, 'Chief Accountant', self::POS_MD],
            [self::POS_LONE, 'Lone Position', null],
            [self::POS_HR, 'Operations & HR Manager', self::POS_MD],
        ] as [$id, $name, $reportsTo]) {
            DB::table('sys_set_staff_positions')->insert(['id' => $id, 'position_name' => $name, 'reports_to_id' => $reportsTo]);
        }

        foreach ([
            [1, 'Mary Director', self::POS_MD, 1],
            [2, 'Zane Zonemanager', self::POS_ZONE_MANAGER, 1],
            [3, 'Zola Zonemanager', self::POS_ZONE_MANAGER, 1],
            [10, 'Felix Officer', self::POS_FIELD_OFFICER, 1],
            [11, 'Fiona Officer', self::POS_FIELD_OFFICER, 1],
            [12, 'Fred NoLogin', self::POS_FIELD_OFFICER, 1],
            [20, 'Cora Chief', self::POS_CHIEF_ACCOUNTANT, 1],
            [21, 'Alan Accountant', self::POS_ACCOUNTANT, 1],
            [30, 'Lola Lone', self::POS_LONE, 1],
            [40, 'Nora Nopost', null, 1],
            [50, 'Frank Left', self::POS_FIELD_OFFICER, 3],
            [60, 'Hana HRManager', self::POS_HR, 1],
        ] as [$id, $name, $position, $status]) {
            DB::table('sys_staff')->insert(['id' => $id, 'full_names' => $name, 'position_id' => $position, 'current_status_id' => $status, 'staff_number' => 'S'.$id]);
        }

        // Roles: the HR manager (staff 60's login) and a director with no staff record
        DB::table('cb_roles')->insert([['id' => 1, 'name' => 'hr_manager'], ['id' => 2, 'name' => 'directors']]);
        DB::table('josya_users')->insert(['id' => 2000, 'name' => 'director', 'staff_id' => null]);

        // Staff with a login: 2, 3, 20, 21, 60 and the field officers 10 and 11
        foreach ([2, 3, 10, 11, 20, 21, 60] as $staffId) {
            DB::table('josya_users')->insert(['id' => 1000 + $staffId, 'name' => 'user'.$staffId, 'staff_id' => null]);
            DB::table('sys_user_staff_links')->insert(['user_id' => 1000 + $staffId, 'staff_id' => $staffId]);
        }
        DB::table('cb_role_user')->insert([['user_id' => 1060, 'role_id' => 1], ['user_id' => 2000, 'role_id' => 2]]);
    }

    /** A signed-in person: linked to the staff member (if any) and holding the given roles. */
    protected function userFor(?int $staffId, array $roles = []): User
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = $staffId ? 1000 + $staffId : 9999;
        $user->shouldReceive('linkedStaff')->andReturn($staffId ? Staff::find($staffId) : null);
        $user->shouldReceive('hasRole')->andReturnUsing(fn ($wanted) => count(array_intersect((array) $wanted, $roles)) > 0);
        $user->shouldReceive('isAbleTo')->andReturn(false);

        return $user;
    }

    /** Names a supervisor for a staff member the way HR does on the Supervisors page. */
    protected function nameSupervisor(int $staffId, int $supervisorId): void
    {
        PerformanceSupervisorAssignment::create(['staff_id' => $staffId, 'supervisor_staff_id' => $supervisorId]);
    }

    protected function hrUser(): User
    {
        return $this->userFor(60, ['hr_manager']);
    }

    /** Criteria for a position; each is [name, weight]. Returns the criteria made. */
    protected function criteria(int $positionId, array $rows = [['Attendance', 1], ['Quality of work', 1]]): array
    {
        $made = [];
        foreach ($rows as $index => [$name, $weight]) {
            $made[] = PerformanceKpi::create(['position_id' => $positionId, 'name' => $name, 'weight' => $weight, 'sort_order' => ($index + 1) * 10, 'is_active' => true]);
        }

        return $made;
    }

    protected function draftCycle(string $name = 'Q1 2027'): PerformanceCycle
    {
        return PerformanceCycle::create(['name' => $name, 'period_start' => '2027-01-01', 'period_end' => '2027-03-31', 'deadline' => '2027-04-30', 'status' => PerformanceCycle::STATUS_DRAFT]);
    }

    /** Publishes a cycle with the field officers' criteria set up and returns it. */
    protected function publishedCycle(array $positions = [self::POS_FIELD_OFFICER, self::POS_ACCOUNTANT]): PerformanceCycle
    {
        foreach ($positions as $position) {
            $this->criteria($position);
        }
        $cycle = $this->draftCycle();
        app(PerformanceCycleService::class)->publish($cycle, $this->hrUser());

        return $cycle->refresh();
    }
}
