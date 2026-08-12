<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_clients', function (Blueprint $table) {
            $table->string('company_name')->nullable();
            $table->string('company_registration_number')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_title')->nullable();
            $table->string('contact_person_mobile')->nullable();
            $table->string('contact_person_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_clients', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_registration_number',
                'contact_person_name',
                'contact_person_title',
                'contact_person_mobile',
                'contact_person_email',
            ]);
        });
    }
};
