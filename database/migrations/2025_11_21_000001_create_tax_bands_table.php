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
        if (!Schema::hasTable('um_tax_bands')) {
            Schema::create('um_tax_bands', function (Blueprint $table) {
                $table->id();
                $table->decimal('range_from', 15, 2);
                $table->decimal('range_to', 15, 2)->nullable();
                $table->decimal('tax_rate', 5, 2);
                $table->boolean('is_last')->default(false);
                $table->string('added_by', 100)->nullable();
                $table->string('updated_by', 100)->nullable();
                $table->timestamps();

                // Unique constraint
                $table->unique(['range_from', 'range_to'], 'unique_tax_band_range');

                // Indexes
                $table->index('range_from', 'um_tax_bands_range_from_index');
                $table->index('range_to', 'um_tax_bands_range_to_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('um_tax_bands');
    }
};
