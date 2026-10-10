<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the password_securities table written by LoginController/AuthController on every password change.
     */
    public function up(): void
    {
        if (Schema::hasTable('password_securities')) {
            return;
        }

        Schema::create('password_securities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('password_updated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_securities');
    }
};
