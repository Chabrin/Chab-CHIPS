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
        if (!Schema::hasTable('action_taken')) {
            Schema::create('action_taken', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique(); // e.g. "Warning", "Suspension", etc.
                $table->timestamps();
            });

            $actions = [
                "Warning Letter",
                "Suspension",
                "Termination"
            ];

            foreach ($actions as $action) {
                \Illuminate\Support\Facades\DB::table('action_taken')->insert([
                    'name' => $action,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_takens');
    }
};
