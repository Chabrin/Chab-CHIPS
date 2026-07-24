<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discipline_case_registrations', function (Blueprint $table) {
            $table->string('decision')->default('Proceed')->after('estimated_closure_date');
            $table->text('rejection_reason')->nullable()->after('decision');
        });
    }

    public function down(): void
    {
        Schema::table('discipline_case_registrations', function (Blueprint $table) {
            $table->dropColumn(['decision', 'rejection_reason']);
        });
    }
};
