<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR > Performance Review. HR edits the criteria each position is rated on (performance_kpis), publishes a cycle, and the
 * system makes one review per staff member: a copy of the criteria (performance_review_items, so later edits never change
 * a past review), who the supervisor is, and the status. Every step is kept in performance_review_events. HR names the
 * supervisor where several people hold the supervising position (performance_supervisor_assignments).
 *
 * No foreign keys, as with the other HR tables: staff and positions are older tables and are linked by id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_kpis')) {
            Schema::create('performance_kpis', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('position_id')->index();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->decimal('weight', 6, 2)->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_cycles')) {
            Schema::create('performance_cycles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->date('period_start');
                $table->date('period_end');
                $table->date('open_date')->nullable();
                $table->date('deadline')->nullable();
                $table->string('status', 20)->default('draft'); // draft, published, closed
                $table->timestamp('published_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_supervisor_assignments')) {
            Schema::create('performance_supervisor_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->unsignedBigInteger('supervisor_staff_id');
                $table->unsignedBigInteger('set_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_reviews')) {
            Schema::create('performance_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cycle_id')->index();
                $table->unsignedBigInteger('staff_id')->index();
                // What the staff member's position and its supervisor position were when the cycle was published
                $table->unsignedBigInteger('position_id')->nullable();
                $table->string('position_name', 150)->nullable();
                $table->unsignedBigInteger('reports_to_position_id')->nullable();
                $table->unsignedBigInteger('supervisor_staff_id')->nullable()->index();
                $table->string('route', 20)->default('hr'); // supervisor, or hr when it goes straight to HR
                $table->string('route_reason', 40)->nullable();
                $table->string('status', 30)->default('awaiting_self'); // awaiting_self, awaiting_supervisor, awaiting_hr, finalised
                $table->boolean('self_skipped')->default(false);
                $table->text('self_summary')->nullable();
                $table->text('supervisor_summary')->nullable();
                $table->text('final_comment')->nullable();
                $table->decimal('final_score', 5, 2)->nullable();
                $table->timestamp('self_submitted_at')->nullable();
                $table->timestamp('supervisor_submitted_at')->nullable();
                $table->timestamp('finalised_at')->nullable();
                $table->unsignedBigInteger('finalised_by')->nullable();
                $table->text('metrics')->nullable(); // the records shown beside the ratings, kept as they were
                $table->timestamp('metrics_taken_at')->nullable();
                $table->timestamps();
                $table->unique(['cycle_id', 'staff_id']);
            });
        }

        if (! Schema::hasTable('performance_review_items')) {
            Schema::create('performance_review_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_id')->index();
                $table->unsignedBigInteger('kpi_id')->nullable()->index();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->decimal('weight', 6, 2)->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedTinyInteger('self_rating')->nullable();
                $table->text('self_comment')->nullable();
                $table->unsignedTinyInteger('supervisor_rating')->nullable();
                $table->text('supervisor_comment')->nullable();
                $table->unsignedTinyInteger('hr_rating')->nullable();
                $table->text('hr_comment')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_review_events')) {
            Schema::create('performance_review_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_id')->index();
                $table->string('action', 40);
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30)->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->unsignedBigInteger('on_behalf_of_staff_id')->nullable();
                $table->text('note')->nullable();
                $table->text('details')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['performance_review_events', 'performance_review_items', 'performance_reviews', 'performance_supervisor_assignments', 'performance_cycles', 'performance_kpis'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
