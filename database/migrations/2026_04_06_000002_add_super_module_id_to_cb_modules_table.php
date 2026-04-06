<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cb_modules', function (Blueprint $table) {
            $table->unsignedBigInteger('super_module_id')->nullable()->after('id');
            $table->foreign('super_module_id')->references('id')->on('cb_super_modules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cb_modules', function (Blueprint $table) {
            $table->dropForeign(['super_module_id']);
            $table->dropColumn('super_module_id');
        });
    }
};
