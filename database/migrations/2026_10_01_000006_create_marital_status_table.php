<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the marital_status table used by the Marital Status settings page.
     * The MaritalStatus model and page exist, but the table was never created in this schema.
     */
    public function up(): void
    {
        if (!Schema::hasTable('marital_status')) {
            Schema::create('marital_status', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 50);
                $table->string('added_by')->nullable();
                $table->integer('deleted_by')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Drop the marital_status table.
     */
    public function down(): void
    {
        Schema::dropIfExists('marital_status');
    }
};
