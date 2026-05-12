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
        Schema::create('cb_tax_default_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_name', 255);
            $table->integer('current_status_id')->default(5); // 5 = Active
            $table->dateTime('date_time')->nullable();
            $table->integer('created_by_id')->nullable();
            $table->index(['current_status_id']);
            $table->index(['created_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_tax_default_types');
    }
};
