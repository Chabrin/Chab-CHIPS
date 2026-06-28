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
        Schema::create('cb_move_in_inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('sla_id')->nullable();
            $table->date('inspection_date');
            $table->string('inspector_name')->nullable();
            $conditions = ['Excellent', 'Good', 'Fair', 'Poor'];
            $table->enum('walls_condition',     $conditions)->nullable();
            $table->enum('floors_condition',    $conditions)->nullable();
            $table->enum('ceiling_condition',   $conditions)->nullable();
            $table->enum('doors_condition',     $conditions)->nullable();
            $table->enum('windows_condition',   $conditions)->nullable();
            $table->enum('plumbing_condition',  $conditions)->nullable();
            $table->enum('electrical_condition',$conditions)->nullable();
            $table->enum('overall_condition',   $conditions)->nullable();
            $table->text('notes')->nullable();
            $table->json('photo_paths')->nullable();
            $table->unsignedBigInteger('conducted_by')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('property_id');
            $table->index('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_move_in_inspections');
    }
};
