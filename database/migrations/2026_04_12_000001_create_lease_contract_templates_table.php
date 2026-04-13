<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_lease_contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name');
            $table->text('description')->nullable();
            $table->longText('html_body');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('current_status_id')->default(1);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('date_time')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_lease_contract_templates');
    }
};
