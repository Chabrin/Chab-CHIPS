<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DepartmentNamesMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use a separate, explicitly in-memory connection; never migrate the local PostgreSQL database.
        config(['database.default' => 'department_names_test']);
        config(['database.connections.department_names_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        Schema::create('sys_set_departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_name');
        });
        Schema::create('sys_set_staff_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('sys_set_departments');
        });
        Schema::create('sys_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('sys_set_departments');
        });
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_28_000001_standardize_hr_department_names.php');
    }

    public function test_names_change_without_losing_assignments_or_touching_other_departments(): void
    {
        DB::table('sys_set_departments')->insert([
            ['id' => 10, 'department_name' => 'ADMINISTRATION'],
            ['id' => 20, 'department_name' => ' accounts '],
            ['id' => 30, 'department_name' => 'FIELD'],
            ['id' => 40, 'department_name' => 'HR'],
            ['id' => 50, 'department_name' => 'Test'],
        ]);
        foreach ([10, 20, 30] as $id) {
            DB::table('sys_staff')->insert(['id' => $id, 'department_id' => $id]);
            DB::table('sys_set_staff_positions')->insert(['id' => $id, 'department_id' => $id]);
        }

        $migration = $this->migration();
        $migration->up();
        $migration->up(); // Retrying must not duplicate or recreate anything.

        $this->assertSame([
            10 => 'Administration & General',
            20 => 'Accounting & Finance',
            30 => 'Property Management',
            40 => 'HR',
            50 => 'Test',
        ], DB::table('sys_set_departments')->orderBy('id')->pluck('department_name', 'id')->all());
        foreach (['sys_staff', 'sys_set_staff_positions'] as $table) {
            $this->assertSame([10 => 10, 20 => 20, 30 => 30],
                DB::table($table)->orderBy('id')->pluck('department_id', 'id')->all());
        }
    }

    public function test_conflicting_department_records_stop_all_renames(): void
    {
        foreach (['Accounting & Finance', ' accounts '] as $conflict) {
            DB::table('sys_set_departments')->delete();
            DB::table('sys_set_departments')->insert([
                ['id' => 1, 'department_name' => 'ADMINISTRATION'],
                ['id' => 2, 'department_name' => 'ACCOUNTS'],
                ['id' => 3, 'department_name' => $conflict],
            ]);
            $before = DB::table('sys_set_departments')->orderBy('id')->pluck('department_name', 'id')->all();

            try {
                $this->migration()->up();
                $this->fail('Duplicate departments must require manual review.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('duplicate departments exist', $exception->getMessage());
            }

            $this->assertSame($before,
                DB::table('sys_set_departments')->orderBy('id')->pluck('department_name', 'id')->all());
        }
    }

    public function test_existing_canonical_names_and_missing_departments_are_preserved_on_rollback(): void
    {
        DB::table('sys_set_departments')->insert([
            'id' => 1, 'department_name' => 'Administration & General',
        ]);

        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $this->assertSame(['Administration & General'],
            DB::table('sys_set_departments')->pluck('department_name')->all());
    }
}
