<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the industries table used by the Industry settings page.
     * The Industry model and page exist, but the table was never created in this schema.
     */
    public function up(): void
    {
        if (!Schema::hasTable('industries')) {
            Schema::create('industries', function (Blueprint $table) {
                $table->increments('id');
                $table->string('industry_name', 100);
                $table->string('description', 200)->nullable();
                $table->string('other', 100)->nullable();
                $table->string('added_by')->nullable();
                $table->timestamp('added_on')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Drop the industries table.
     */
    public function down(): void
    {
        Schema::dropIfExists('industries');
    }
};
