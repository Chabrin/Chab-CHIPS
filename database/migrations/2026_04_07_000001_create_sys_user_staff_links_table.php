<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_user_staff_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('staff_id')->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('josya_users')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('sys_staff')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_user_staff_links');
    }
};
