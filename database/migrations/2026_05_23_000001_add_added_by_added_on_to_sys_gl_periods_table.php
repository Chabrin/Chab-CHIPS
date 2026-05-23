<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_gl_periods', function (Blueprint $table) {
            $table->integer('added_by')->nullable()->after('current_status_id');
            $table->timestamp('added_on')->nullable()->after('added_by');
        });
    }

    public function down(): void
    {
        Schema::table('sys_gl_periods', function (Blueprint $table) {
            $table->dropColumn(['added_by', 'added_on']);
        });
    }
};
