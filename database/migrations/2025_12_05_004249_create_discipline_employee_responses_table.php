<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_employee_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->date('response_date');
            $table->text('response_text');
            
            $table->boolean('admits_allegations')->nullable();
            $table->text('mitigating_circumstances')->nullable();
            
            $table->unsignedBigInteger('submitted_by')->nullable()->comment('Could be employee or representative');
            $table->timestamp('submitted_at')->useCurrent();
            
            $table->unsignedBigInteger('received_by')->nullable();
            $table->dateTime('received_at')->nullable();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('submitted_by')->references('id')->on('users');
            // $table->foreign('received_by')->references('id')->on('users');
            
            $table->unique('case_id', 'unique_case_response');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_employee_responses');
    }
};