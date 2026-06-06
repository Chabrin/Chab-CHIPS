<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_tenant_exit_inspections', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('exit_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('unit_id');

            $table->date('inspection_date');

            $conditionValues = ['Excellent', 'Good', 'Fair', 'Poor', 'Very Poor'];
            $table->enum('overall_condition',   $conditionValues);
            $table->enum('walls_condition',     $conditionValues)->nullable();
            $table->enum('floors_condition',    $conditionValues)->nullable();
            $table->enum('ceiling_condition',   $conditionValues)->nullable();
            $table->enum('doors_condition',     $conditionValues)->nullable();
            $table->enum('windows_condition',   $conditionValues)->nullable();
            $table->enum('plumbing_condition',  $conditionValues)->nullable();
            $table->enum('electrical_condition',$conditionValues)->nullable();

            $table->enum('keys_returned', ['Yes', 'No', 'Partial']);
            $table->string('keys_notes', 500)->nullable();

            $table->text('damage_description')->nullable();
            $table->decimal('damage_amount', 12, 2)->nullable();

            $table->enum('cleaning_required', ['Yes', 'No']);
            $table->decimal('cleaning_cost', 12, 2)->nullable();

            $table->text('general_notes')->nullable();
            $table->string('inspected_by_name', 255)->nullable();

            // JSON array of Storage paths
            $table->json('photo_paths')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('exit_id')->references('id')->on('cb_tenant_exits')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('josya_users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('exit_id');
            $table->index('tenant_id');
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_exit_inspections');
    }
};
