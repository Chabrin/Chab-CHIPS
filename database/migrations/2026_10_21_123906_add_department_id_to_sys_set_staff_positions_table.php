<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link each staff position to the department it belongs to. Safe to re-run.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('sys_set_staff_positions', 'department_id')) {
            Schema::table('sys_set_staff_positions', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('id');
            });
        }

        if (!$this->hasDepartmentForeignKey()) {
            Schema::table('sys_set_staff_positions', function (Blueprint $table) {
                $table->foreign('department_id')
                      ->references('id')
                      ->on('sys_set_departments')
                      ->nullOnDelete();
            });
        }
    }

    /**
     * Drop the department link from staff positions. Safe to re-run.
     */
    public function down(): void
    {
        if ($this->hasDepartmentForeignKey()) {
            Schema::table('sys_set_staff_positions', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
            });
        }

        if (Schema::hasColumn('sys_set_staff_positions', 'department_id')) {
            Schema::table('sys_set_staff_positions', function (Blueprint $table) {
                $table->dropColumn('department_id');
            });
        }
    }

    /**
     * Whether a foreign key on sys_set_staff_positions.department_id already exists.
     */
    private function hasDepartmentForeignKey(): bool
    {
        foreach (Schema::getForeignKeys('sys_set_staff_positions') as $foreignKey) {
            if ($foreignKey['columns'] === ['department_id']) {
                return true;
            }
        }

        return false;
    }
};
