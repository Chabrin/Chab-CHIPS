<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the currencies table used by the Currencies settings page.
     * The Currency model and page exist, but the table was never created in this schema.
     */
    public function up(): void
    {
        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->increments('id');
                $table->string('currency_name', 100);
                $table->string('currency_code', 10);
                $table->integer('country_id')->nullable();
                $table->string('added_by')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Drop the currencies table.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
