<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the control_categories table used by the Control Categories settings page.
     * The model and cb_system_configs.category_id already exist, but the table was never created.
     */
    public function up(): void
    {
        if (!Schema::hasTable('control_categories')) {
            Schema::create('control_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('category_name', 50);
                $table->string('display_name', 50);
                $table->integer('precedence')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Drop the control_categories table.
     */
    public function down(): void
    {
        Schema::dropIfExists('control_categories');
    }
};
