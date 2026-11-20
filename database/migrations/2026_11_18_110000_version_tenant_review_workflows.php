<?php

use App\Services\TenantReviewWorkflowService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Resolve roles before making schema changes so a missing role is actionable.
        $steps = app(TenantReviewWorkflowService::class)->defaultSteps();
        Schema::create('tenant_review_workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->json('steps');
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('tenant_review_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('published_version_id')->constrained('tenant_review_workflow_versions');
        });
        $version = DB::table('tenant_review_workflow_versions')->insertGetId([
            'number' => 1, 'steps' => json_encode($steps), 'created_at' => now(),
        ]);
        DB::table('tenant_review_workflows')->insert(['id' => 1, 'published_version_id' => $version]);
        Schema::table('tenant_reviews', function (Blueprint $table) {
            $table->foreignId('workflow_version_id')->nullable()->constrained('tenant_review_workflow_versions');
        });
        DB::table('tenant_reviews')->whereNull('workflow_version_id')->update(['workflow_version_id' => $version]);
    }

    public function down(): void
    {
        if (DB::table('tenant_reviews')->whereNotNull('workflow_version_id')->exists()) {
            throw new RuntimeException('Tenant review workflow history must be preserved while reviews use it.');
        }
        Schema::table('tenant_reviews', fn (Blueprint $table) => $table->dropConstrainedForeignId('workflow_version_id'));
        Schema::dropIfExists('tenant_review_workflows');
        Schema::dropIfExists('tenant_review_workflow_versions');
    }
};
