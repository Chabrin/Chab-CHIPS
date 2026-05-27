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
        Schema::create('clients_bank_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('bank_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('account_number', 50);
            $table->string('account_name', 255);
            $table->integer('is_default')->default(0);
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamp('added_on')->nullable();

            $table->unique(['bank_id', 'account_number'], 'unique_bank_account');

            $table->foreign('client_id')->references('id')->on('sys_pm_clients')->onDelete('cascade');
            $table->foreign('bank_id')->references('id')->on('sys_set_banks')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('sys_set_banks_branches')->onDelete('set null');
            $table->foreign('added_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('client_id');
            $table->index('bank_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients_bank_details');
    }
};
