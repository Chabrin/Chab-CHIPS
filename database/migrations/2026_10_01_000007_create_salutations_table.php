<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the salutations table used by the Salutation settings page.
     * The Salutation model and page exist, but the table was never created in this schema.
     * The model has timestamps disabled, so created_at defaults to now and updated_at stays nullable.
     */
    public function up(): void
    {
        if (!Schema::hasTable('salutations')) {
            Schema::create('salutations', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 50);
                $table->string('description', 255)->nullable();
                $table->string('added_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->integer('deleted_by')->nullable();
            });
        }
    }

    /**
     * Drop the salutations table.
     */
    public function down(): void
    {
        Schema::dropIfExists('salutations');
    }
};
