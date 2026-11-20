<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Review, the joint review meeting and the appeal (HR's "Performance Review Process"):
 *
 *   performance_settings           small key/value settings (the least committee members who must attend, the appeal period)
 *   performance_committee_members  the staff HR may pick for a meeting
 *   performance_meetings           one joint review meeting per review (kind review) and one appeal hearing per review (kind appeal)
 *   performance_meeting_attendees  who was invited and, once recorded, who came
 *   performance_meeting_items      what was discussed: the four standard areas plus any extra items
 *   performance_appeals            a staff member's appeal against the finalised review, and the committee's decision
 *   performance_appeal_items       the criteria the appeal is about, each with the staff member's reason
 *
 * Only adds tables; nothing existing is changed. Written so it can be run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_settings')) {
            Schema::create('performance_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 60)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_committee_members')) {
            Schema::create('performance_committee_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->unsignedBigInteger('added_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_appeals')) {
            Schema::create('performance_appeals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_id')->unique(); // one appeal per review
                $table->unsignedBigInteger('filed_by_user_id')->nullable();
                $table->boolean('on_behalf')->default(false); // HR filed it for the staff member
                $table->text('late_reason')->nullable(); // why HR accepted it after the appeal period
                $table->text('reason')->nullable(); // the staff member's overall words
                $table->string('status', 20)->default('filed'); // filed, decided
                $table->string('outcome', 20)->nullable(); // upheld, partly_upheld, rejected
                $table->text('decision_reason')->nullable();
                $table->decimal('score_before', 5, 2)->nullable();
                $table->decimal('score_after', 5, 2)->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_appeal_items')) {
            Schema::create('performance_appeal_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('appeal_id')->index();
                $table->unsignedBigInteger('review_item_id');
                $table->text('reason');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_meetings')) {
            Schema::create('performance_meetings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_id')->index();
                $table->string('kind', 10)->default('review'); // review (the joint review meeting) or appeal (the appeal hearing)
                $table->unsignedBigInteger('appeal_id')->nullable()->index();
                $table->string('status', 20)->default('scheduled'); // scheduled, held
                $table->dateTime('scheduled_at');
                $table->string('venue', 150)->nullable();
                $table->unsignedBigInteger('hr_user_id')->nullable(); // the HR person invited
                $table->unsignedSmallInteger('reschedule_count')->default(0);
                $table->unsignedBigInteger('scheduled_by')->nullable();
                $table->timestamp('held_at')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->timestamps();
                $table->unique(['review_id', 'kind']);
            });
        }

        if (! Schema::hasTable('performance_meeting_attendees')) {
            Schema::create('performance_meeting_attendees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->index();
                $table->string('role', 12); // staff, supervisor, hr, committee
                $table->unsignedBigInteger('staff_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('name', 150);
                $table->boolean('present')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('performance_meeting_items')) {
            Schema::create('performance_meeting_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->index();
                $table->string('section', 30); // skill_gaps, training_needs, performance_feedback, goals, other
                $table->string('title', 150);
                $table->text('notes')->nullable();
                $table->boolean('approved')->default(false); // the committee approved it (training needs and goals)
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['performance_meeting_items', 'performance_meeting_attendees', 'performance_meetings', 'performance_appeal_items', 'performance_appeals', 'performance_committee_members', 'performance_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
