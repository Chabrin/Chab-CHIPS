<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_staff', function (Blueprint $table) {
            $table->string('employment_type')->default('Permanent')->after('full_names'); 
            // Options: Permanent, Temporary, Casual
        });
    }

    public function down(): void
    {
        Schema::table('sys_staff', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
