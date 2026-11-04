<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ELEMENTS = 'sys_hr_payroll_elements';

    /** Calculated by the payroll run for every staff member, so they are always recurring for all staff. */
    private const STATUTORY_KEYS = ['nssf', 'nhif', 'housing_levy', 'payee'];

    /**
     * Give each payroll element a "how often" (recurring or one-time) and an "applies to" (all staff or specific
     * staff) setting, and create the tables for one-time items entered per payroll month and for the log of
     * salary and recurring element changes. Existing elements keep behaving as they do today: the four statutory
     * items apply to all staff and everything else is recurring for specific staff. Safe to run again.
     */
    public function up(): void
    {
        if (Schema::hasTable(self::ELEMENTS)) {
            Schema::table(self::ELEMENTS, function (Blueprint $table) {
                if (!Schema::hasColumn(self::ELEMENTS, 'recurrence')) {
                    $table->string('recurrence', 20)->default('recurring');
                }
                if (!Schema::hasColumn(self::ELEMENTS, 'applies_to')) {
                    $table->string('applies_to', 20)->default('specific');
                }
            });

            DB::table(self::ELEMENTS)->whereIn('element_key', self::STATUTORY_KEYS)->update(['recurrence' => 'recurring', 'applies_to' => 'all']);
        }

        if (!Schema::hasTable('payroll_period_items')) {
            Schema::create('payroll_period_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('period_id')->index();
                $table->unsignedBigInteger('staff_id')->nullable()->index(); // null: every staff member in the run
                $table->unsignedBigInteger('element_id');
                $table->decimal('amount', 15, 2);
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contract_changes')) {
            Schema::create('contract_changes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_id')->nullable()->index();
                $table->unsignedBigInteger('staff_id')->index();
                $table->string('field', 30); // contract_created, basic_salary, element_added, element_removed, element_changed
                $table->unsignedBigInteger('element_id')->nullable();
                $table->string('old_value', 50)->nullable();
                $table->string('new_value', 50)->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    /**
     * Drop the new tables and the two element settings.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_changes');
        Schema::dropIfExists('payroll_period_items');

        if (Schema::hasTable(self::ELEMENTS)) {
            Schema::table(self::ELEMENTS, function (Blueprint $table) {
                foreach (['recurrence', 'applies_to'] as $column) {
                    if (Schema::hasColumn(self::ELEMENTS, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
