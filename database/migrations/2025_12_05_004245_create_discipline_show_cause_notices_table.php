<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_show_cause_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->string('notice_number', 50)->nullable();
            $table->date('issue_date');
            $table->date('response_deadline');
            
            $table->text('allegations');
            $table->text('potential_consequences')->nullable();
            
            $table->unsignedBigInteger('issued_by');
            $table->enum('delivered_method', [
                'Hand Delivery', 'Email', 'Registered Mail', 'Courier'
            ]);
            $table->dateTime('delivered_at')->nullable();
            
            $table->boolean('acknowledgment_received')->default(false);
            $table->dateTime('acknowledgment_date')->nullable();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('issued_by')->references('id')->on('users');
            
            $table->unique('case_id', 'unique_case_notice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_show_cause_notices');
    }
};
