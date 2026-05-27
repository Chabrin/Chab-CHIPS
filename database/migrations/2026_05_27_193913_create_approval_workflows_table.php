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
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_name', 255);
            $table->string('description', 500)->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamp('added_on')->nullable();

            $table->foreign('added_by')->references('id')->on('josya_users')->onDelete('set null');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
};
