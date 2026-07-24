<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_debtors_invoices', function (Blueprint $table) {
            // Ties an invoice back to the generation run (sys_pm_generated_invoice_months.batch_number)
            // that created it. Nullable: manually-created invoices have no batch.
            $table->string('batch_number', 20)->nullable()->after('reference_number');
            $table->index('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_debtors_invoices', function (Blueprint $table) {
            $table->dropIndex(['batch_number']);
            $table->dropColumn('batch_number');
        });
    }
};
