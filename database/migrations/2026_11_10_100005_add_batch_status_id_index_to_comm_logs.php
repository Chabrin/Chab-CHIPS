<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A batch's recipient list is read one page at a time, in id order, optionally for one status.
     * (batch_id, status, id) serves that straight from the index, with no sort over the batch's
     * rows, and it covers everything the old single-column batch_id index did, so that one goes.
     */
    public function up(): void
    {
        foreach (['cb_email_logs', 'cb_sms_logs'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $name = $table . '_batch_status_id_idx';

            if (!Schema::hasIndex($table, $name)) {
                Schema::table($table, function (Blueprint $t) use ($name) {
                    $t->index(['batch_id', 'status', 'id'], $name);
                });
            }

            if (Schema::hasIndex($table, $table . '_batch_id_index')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex($table . '_batch_id_index');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['cb_email_logs', 'cb_sms_logs'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (!Schema::hasIndex($table, $table . '_batch_id_index')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->index('batch_id');
                });
            }

            if (Schema::hasIndex($table, $table . '_batch_status_id_idx')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex($table . '_batch_status_id_idx');
                });
            }
        }
    }
};
