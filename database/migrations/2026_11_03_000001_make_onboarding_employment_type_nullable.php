<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Invitations now carry only the recipient email; HR picks the staff
    // category on the Add Staff form after reviewing the submission.
    public function up(): void
    {
        Schema::table('staff_onboarding_invitations', function (Blueprint $table) {
            $table->string('employment_type', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('staff_onboarding_invitations', function (Blueprint $table) {
            $table->string('employment_type', 32)->nullable(false)->change();
        });
    }
};
