<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // How the next of kin is related to the staff member (Spouse, Parent, ...). The name stays in kins_names.
    public function up(): void
    {
        if (!Schema::hasColumn('sys_staff', 'kins_relationship')) {
            Schema::table('sys_staff', function (Blueprint $table) {
                $table->string('kins_relationship', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sys_staff', 'kins_relationship')) {
            Schema::table('sys_staff', function (Blueprint $table) {
                $table->dropColumn('kins_relationship');
            });
        }
    }
};
