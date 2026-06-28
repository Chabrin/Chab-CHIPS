<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_tenant_exit_inspections', function (Blueprint $table) {
            $table->json('video_paths')->nullable()->after('photo_paths');
        });

        Schema::table('cb_move_in_inspections', function (Blueprint $table) {
            $table->json('video_paths')->nullable()->after('photo_paths');
        });
    }

    public function down(): void
    {
        Schema::table('cb_tenant_exit_inspections', function (Blueprint $table) {
            $table->dropColumn('video_paths');
        });

        Schema::table('cb_move_in_inspections', function (Blueprint $table) {
            $table->dropColumn('video_paths');
        });
    }
};
