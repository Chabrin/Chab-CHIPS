<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_deductions_perperiod', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('staff_number', 50)->nullable(); // raw EMPNUMB, kept alongside staff_id

            $table->unsignedBigInteger('payroll_element_id')->nullable();
            $table->string('element_code', 50)->nullable(); // raw EDCODE, kept alongside payroll_element_id

            $table->integer('payroll_year')->nullable();
            $table->integer('payroll_month')->nullable();
            $table->decimal('amount', 15, 2)->default(0);

            $table->boolean('is_locked')->default(true); // source LOCKD: 99% of rows are locked (1)

            // Mixed format in source (numeric ids like "137" and usernames like
            // "murungi") — cannot be reliably resolved to a single FK, kept as
            // raw text. Only ~5% of rows have this populated.
            $table->string('audit_user', 30)->nullable();
            $table->timestamp('audited_at')->nullable();

            $table->timestamps();

            $table->foreign('staff_id')->references('id')->on('sys_staff')->onDelete('set null');
            $table->foreign('payroll_element_id')->references('id')->on('sys_hr_payroll_elements')->onDelete('set null');

            $table->index('staff_id');
            $table->index('payroll_element_id');
            $table->index(['payroll_year', 'payroll_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_deductions_perperiod');
    }
};
