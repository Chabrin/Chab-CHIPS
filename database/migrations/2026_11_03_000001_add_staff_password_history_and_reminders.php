<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('password_hash');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'password_hash']);
            $table->foreign('user_id')->references('id')->on('josya_users')->onDelete('cascade');
        });
        Schema::create('password_expiry_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('days_before');
            $table->unique(['user_id', 'expires_at', 'days_before'], 'password_reminder_unique');
            $table->foreign('user_id')->references('id')->on('josya_users')->onDelete('cascade');
        });

        $activation = now();
        DB::table('josya_users')->select(['id', 'password'])->orderBy('id')->chunkById(500, function ($users) use ($activation) {
            foreach ($users as $user) {
                if ($user->password) {
                    DB::table('password_histories')->insert([
                        'user_id' => $user->id, 'password_hash' => $user->password, 'created_at' => $activation,
                    ]);
                }
                DB::table('password_securities')->insertOrIgnore([
                    'user_id' => $user->id, 'password_changed_at' => $activation,
                    'password_updated_at' => $activation, 'created_at' => $activation, 'updated_at' => $activation,
                ]);
                // Preserve known change dates; grant a first 30-day period only when unknown.
                DB::table('password_securities')->where('user_id', $user->id)
                    ->whereNull('password_changed_at')->whereNull('password_updated_at')
                    ->update(['password_changed_at' => $activation, 'password_updated_at' => $activation]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_expiry_reminders');
        Schema::dropIfExists('password_histories');
    }
};
