<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('sys_pm_clients')->cascadeOnDelete();

            $table->string('name', 255);
            $table->string('national_id', 50)->nullable();
            $table->string('kra_pin', 50)->nullable();
            $table->string('phone', 20)->nullable();

            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients_partners');
    }
};
