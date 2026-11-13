<?php

namespace Tests\Feature;

use App\Http\Controllers\HrSettingsController;
use App\Models\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Positions and departments: position names are unique regardless of case, and a missing position or department is a 404.
 */
class HrOrgStructureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->integer('position_id')->nullable();
        });
        Schema::create('sys_set_departments', function (Blueprint $t) {
            $t->id();
            $t->string('department_name');
            $t->dateTime('date_time')->nullable();
        });
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

        DB::table('sys_set_departments')->insert([
            ['id' => 1, 'department_name' => 'Property Management'],
            ['id' => 2, 'department_name' => 'Administration'],
        ]);
        DB::table('sys_set_staff_positions')->insert([
            ['id' => 7, 'position_name' => 'Property Manager', 'department_id' => 1],
            ['id' => 8, 'position_name' => 'Assistant Property Manager', 'department_id' => 1],
            ['id' => 9, 'position_name' => 'Zone Manager', 'department_id' => 1],
        ]);
    }

    private function userWhoCan(string ...$permissions): \App\Models\User
    {
        $user = \Mockery::mock(\App\Models\User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = 7;

        return $user;
    }

    private function controller(): HrSettingsController
    {
        return app(HrSettingsController::class);
    }

    private function updateRequest(int $departmentId, string $name): Request
    {
        return Request::create('/', 'POST', ['department_id' => $departmentId, 'position_name' => $name]);
    }

    public function test_position_names_are_unique_regardless_of_case(): void
    {
        $response = $this->controller()->positionsStoreAjax(Request::create('/', 'POST', ['department_id' => 1, 'position_name' => 'zone manager']));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(3, Position::count());

        $update = $this->controller()->positionsUpdateAjax($this->updateRequest(1, 'ZONE MANAGER'), 7);
        $this->assertSame(422, $update->getStatusCode());
    }

    public function test_changing_a_missing_position_or_department_is_a_404(): void
    {
        $this->assertSame(404, $this->controller()->positionsUpdateAjax($this->updateRequest(1, 'Anything'), 999)->getStatusCode());
        $this->actingAs($this->userWhoCan('delete_staff_positions'));
        $this->assertSame(404, $this->controller()->positionsDestroyAjax(999)->getStatusCode());
        $this->assertSame(404, $this->controller()->departmentUpdate(Request::create('/', 'POST', ['department_name' => 'X']), 999)->getStatusCode());
    }
}
