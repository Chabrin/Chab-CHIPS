<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the columns the Gender and Salutation settings pages need to cb_genders and cb_salutations.
     * These tables replace the earlier gender and salutations tables.
     */
    public function up(): void
    {
        if (Schema::hasTable('cb_genders')) {
            Schema::table('cb_genders', function (Blueprint $table) {
                if (!Schema::hasColumn('cb_genders', 'description')) {
                    $table->string('description', 255)->nullable()->after('name');
                }
                if (!Schema::hasColumn('cb_genders', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('cb_genders', 'deleted_by')) {
                    $table->string('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('cb_salutations')) {
            Schema::table('cb_salutations', function (Blueprint $table) {
                if (!Schema::hasColumn('cb_salutations', 'description')) {
                    $table->string('description', 255)->nullable()->after('name');
                }
                if (!Schema::hasColumn('cb_salutations', 'added_by')) {
                    $table->string('added_by')->nullable();
                }
                if (!Schema::hasColumn('cb_salutations', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable();
                }
                if (!Schema::hasColumn('cb_salutations', 'deleted_by')) {
                    $table->integer('deleted_by')->nullable();
                }
            });
        }
    }

    /**
     * Remove the added columns.
     */
    public function down(): void
    {
        if (Schema::hasTable('cb_genders')) {
            Schema::table('cb_genders', function (Blueprint $table) {
                $table->dropColumn(['description', 'deleted_at', 'deleted_by']);
            });
        }

        if (Schema::hasTable('cb_salutations')) {
            Schema::table('cb_salutations', function (Blueprint $table) {
                $table->dropColumn(['description', 'added_by', 'deleted_at', 'deleted_by']);
            });
        }
    }
};
