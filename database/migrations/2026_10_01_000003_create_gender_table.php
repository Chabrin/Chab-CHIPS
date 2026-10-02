<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the gender table used by the Gender settings page.
     * The Gender model and page exist, but the table was never created in this schema.
     */
    public function up(): void
    {
        if (!Schema::hasTable('gender')) {
            Schema::create('gender', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 50);
                $table->string('description', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->string('deleted_by')->nullable();
            });
        }
    }

    /**
     * Drop the gender table.
     */
    public function down(): void
    {
        Schema::dropIfExists('gender');
    }
};
