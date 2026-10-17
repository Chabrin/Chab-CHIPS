<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_training_records')) {
            Schema::create('staff_training_records', function (Blueprint $table) {
                $table->id();
            
                // Matches sys_staff.id (integer)
                $table->integer('staff_id');
                $table->foreign('staff_id')
                      ->references('id')
                      ->on('sys_staff')
                      ->onDelete('cascade');

                $table->unsignedBigInteger('training_material_id');
                $table->foreign('training_material_id')
                      ->references('id')
                      ->on('training_materials')
                      ->onDelete('cascade');

                $table->string('version_acknowledged', 20)->default('1.0');
                $table->timestamp('completed_at')->nullable();
                $table->date('due_date')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->unique(['staff_id', 'training_material_id', 'version_acknowledged'], 'staff_mat_ver_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_training_records');
    }
};