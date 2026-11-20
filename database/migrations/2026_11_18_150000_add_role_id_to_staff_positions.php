<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every position has one system role (cb_roles). A new position gets its role when it is created, so the Position and Role lists
 * always match. The role starts with no access; an administrator gives it menus and permissions on the Roles page.
 *
 * Existing positions are linked to roles with `php artisan hr:link-position-roles` (it shows what it would do first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_set_staff_positions', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_set_staff_positions', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sys_set_staff_positions', function (Blueprint $table) {
            if (Schema::hasColumn('sys_set_staff_positions', 'role_id')) {
                $table->dropColumn('role_id');
            }
        });
    }
};
