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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. "Lateness", "Theft", etc.
            $table->timestamps();
        });

        // Seed default incidents
        $incidents = [
            "Lateness",
            "Theft",
            "Unauthorised possession of company goods",
            "Fraud",
            "Bribery",
            "Clock card fraud",
            "Threatening conduct",
            "Intimidation",
            "Sleeping on duty",
            "Fighting at work",
            "Assault",
            "Negligence",
            "Wilful poor performance",
            "Sabotage",
            "Sexual or racial harassment"
        ];

        foreach ($incidents as $incident) {
            \Illuminate\Support\Facades\DB::table('incidents')->insert([
                'name' => $incident,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
