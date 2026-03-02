<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_witness_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->string('witness_name', 255);
            $table->unsignedBigInteger('witness_staff_id')->nullable()->comment('If internal employee');
            $table->string('witness_contact', 255)->nullable();
            $table->string('witness_role', 255)->nullable()->comment('Eyewitness, Character witness, etc');
            
            $table->text('statement_text');
            $table->date('statement_date');
            
            $table->boolean('is_sworn')->default(false);
            $table->string('sworn_before', 255)->nullable();
            
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('recorded_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('witness_staff_id')->references('id')->on('staff');
            // $table->foreign('recorded_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_witness_statements');
    }
};
