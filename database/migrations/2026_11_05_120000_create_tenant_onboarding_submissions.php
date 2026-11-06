<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_onboarding_submissions', function (Blueprint $table) {
            $table->id();
            $table->integer('property_id')->index();
            $table->integer('unit_id');
            $table->integer('reserved_unit_id')->nullable()->unique();
            $table->unsignedBigInteger('prepared_by')->index();
            $table->string('state', 30)->default('pending')->index();
            $table->string('stage', 30)->default('field_officer')->index();
            $table->unsignedInteger('revision')->default(1);
            $table->text('payload');
            $table->json('documents');
            $table->unsignedBigInteger('batch_tenant_id')->nullable()->unique();
            $table->integer('tenant_id')->nullable()->unique();
            $table->integer('lease_id')->nullable()->unique();
            $table->integer('invoice_id')->nullable()->unique();
            $table->unsignedBigInteger('effected_by')->nullable();
            $table->timestamp('effected_at')->nullable();
            $table->timestamps();
        });
        Schema::create('tenant_onboarding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('tenant_onboarding_submissions');
            $table->unsignedInteger('revision');
            $table->string('stage', 30);
            $table->string('action', 30);
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->text('comment')->nullable();
            $table->text('snapshot')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_onboarding_events');
        Schema::dropIfExists('tenant_onboarding_submissions');
    }
};
