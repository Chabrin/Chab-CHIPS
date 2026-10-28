<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contract_reminders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('contract_id');
            $table->date('expiry_date');
            $table->integer('days_before');
            $table->bigInteger('user_id');
            $table->timestamp('created_at');
            $table->unique(['contract_id', 'expiry_date', 'days_before', 'user_id'], 'employee_contract_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contract_reminders');
    }
};
