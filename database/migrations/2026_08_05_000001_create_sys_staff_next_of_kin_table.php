<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_staff_next_of_kin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('staff_number', 10)->nullable();
            $table->string('kin_name', 50)->nullable();
            $table->string('relationship', 30)->nullable();
            $table->string('kin_type', 30)->nullable();
            $table->string('kin_address', 50)->nullable();
            $table->string('kin_city', 30)->nullable();
            $table->string('kin_phone', 20)->nullable();
            $table->timestamp('date_time')->useCurrent();

            $table->foreign('staff_id')->references('id')->on('sys_staff')->nullOnDelete();
            $table->index('staff_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_staff_next_of_kin');
    }
};
