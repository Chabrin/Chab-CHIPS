<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_incident_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->dateTime('incident_date_time');
            $table->string('location', 255)->nullable();
            $table->unsignedBigInteger('incident_type_id')->nullable()->comment('FK to incidents table');
            $table->text('incident_description');
            $table->text('immediate_action_taken')->nullable();
            
            $table->unsignedBigInteger('reported_by')->comment('Person reporting (may differ from created_by)');
            $table->boolean('witness_present')->default(false);
            $table->boolean('police_involved')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            $table->foreign('incident_type_id')->references('id')->on('incidents');
            // $table->foreign('reported_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_incident_reports');
    }
};
