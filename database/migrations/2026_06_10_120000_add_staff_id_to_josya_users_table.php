<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('josya_users', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('josya_users', function (Blueprint $table) {
            $table->dropColumn('staff_id');
        });
    }
};
