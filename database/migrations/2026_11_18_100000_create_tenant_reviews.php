<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->unsignedBigInteger('lease_id')->unique();
            $table->unsignedBigInteger('prepared_by');
            $table->string('stage')->default('zone_manager');
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('accounts_pending')->default(false);
            $table->timestamps();
            $table->index(['stage', 'accounts_pending']);
        });
        Schema::create('tenant_review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('tenant_reviews');
            $table->unsignedInteger('revision');
            $table->unsignedBigInteger('actor_id');
            $table->string('stage');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->text('snapshot')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_review_events');
        Schema::dropIfExists('tenant_reviews');
    }
};
