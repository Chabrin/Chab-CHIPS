<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discipline_hearing_minutes', function (Blueprint $table) {
            $table->text('panel_recommendation')->nullable()->after('panel_questions');
            $table->text('signature_attestation')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('discipline_hearing_minutes', function (Blueprint $table) {
            $table->dropColumn(['panel_recommendation', 'signature_attestation']);
        });
    }
};
