<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // A draft is also the record of an onboarding that was started on Add Staff. These columns let the Staff onboarding page
    // follow it: the state of each stage (a tab), the invitation it came from, the staff number reserved for it while it is a draft,
    // and, once the staff member is saved, who they are and when it was finished. The form's own fields stay in the encrypted payload.
    public function up(): void
    {
        if (!Schema::hasTable('staff_drafts')) {
            return;
        }

        Schema::table('staff_drafts', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_drafts', 'stages')) {
                $table->text('stages')->nullable();
            }
            if (!Schema::hasColumn('staff_drafts', 'onboarding_invitation_id')) {
                $table->unsignedBigInteger('onboarding_invitation_id')->nullable()->index();
            }
            if (!Schema::hasColumn('staff_drafts', 'employment_type')) {
                $table->string('employment_type', 40)->nullable();
            }
            if (!Schema::hasColumn('staff_drafts', 'staff_number')) {
                $table->string('staff_number', 50)->nullable();
            }
            if (!Schema::hasColumn('staff_drafts', 'staff_id')) {
                $table->unsignedBigInteger('staff_id')->nullable()->index();
            }
            if (!Schema::hasColumn('staff_drafts', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('staff_drafts')) {
            return;
        }

        Schema::table('staff_drafts', function (Blueprint $table) {
            foreach (['onboarding_invitation_id', 'staff_id'] as $indexed) {
                if (Schema::hasColumn('staff_drafts', $indexed)) {
                    $table->dropIndex([$indexed]);
                }
            }
        });

        Schema::table('staff_drafts', function (Blueprint $table) {
            foreach (['stages', 'onboarding_invitation_id', 'employment_type', 'staff_number', 'staff_id', 'completed_at'] as $column) {
                if (Schema::hasColumn('staff_drafts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
