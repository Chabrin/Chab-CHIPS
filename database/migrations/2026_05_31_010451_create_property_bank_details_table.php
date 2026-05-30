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
        Schema::create('property_bank_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('client_bank_id');
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamp('added_on')->useCurrent();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('sys_pm_properties')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('sys_pm_clients')->onDelete('cascade');
            $table->foreign('client_bank_id')->references('id')->on('clients_bank_details')->onDelete('cascade');
            $table->foreign('added_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->unique(['property_id', 'client_bank_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_bank_details');
    }
};
