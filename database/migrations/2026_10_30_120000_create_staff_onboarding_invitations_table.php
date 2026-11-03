<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_onboarding_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_email');
            $table->string('token_hash', 64)->unique();
            $table->string('employment_type', 32);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->date('hire_date')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->unsignedBigInteger('invited_by');
            $table->string('status', 20)->default('sent');
            $table->text('submission')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Storage::disk('local')->deleteDirectory('staff_onboarding');
        Schema::dropIfExists('staff_onboarding_invitations');
    }
};
