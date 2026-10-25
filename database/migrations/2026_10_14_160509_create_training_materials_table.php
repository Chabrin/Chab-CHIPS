<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('training_materials')) {
            Schema::create('training_materials', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('file_path');
                $table->string('file_name');
                $table->string('file_type', 50);
                $table->string('version')->default('1.0');
                $table->boolean('is_mandatory')->default(true);
            
                // Matches sys_staff.id (integer)
                $table->integer('uploaded_by')->nullable();
                $table->foreign('uploaded_by')
                      ->references('id')
                      ->on('sys_staff')
                      ->onDelete('set null');

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_materials');
    }
};
