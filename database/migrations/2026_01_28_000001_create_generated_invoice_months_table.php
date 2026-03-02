<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sys_pm_generated_invoice_months', function (Blueprint $table) {
            $table->id();
            $table->string('month_year', 7)->unique()->comment('Format: YYYY-MM');
            $table->unsignedBigInteger('invoice_count')->default(0)->comment('Number of invoices generated');
            $table->unsignedBigInteger('generated_by')->nullable()->comment('User who generated the invoices');
            $table->timestamp('generated_at')->useCurrent()->comment('When the invoices were generated');
            $table->text('notes')->nullable()->comment('Additional notes or metadata');

            $table->index('month_year');
            $table->index('generated_at');
        });

        // Pre-insert October, November, and December 2025 as already generated
        DB::table('sys_pm_generated_invoice_months')->insert([
            [
                'month_year' => '2025-10',
                'invoice_count' => 0,
                'generated_by' => null,
                'generated_at' => '2025-10-05 00:00:00',
                'notes' => 'Pre-seeded: Historical data'
            ],
            [
                'month_year' => '2025-11',
                'invoice_count' => 0,
                'generated_by' => null,
                'generated_at' => '2025-11-05 00:00:00',
                'notes' => 'Pre-seeded: Historical data'
            ],
            [
                'month_year' => '2025-12',
                'invoice_count' => 0,
                'generated_by' => null,
                'generated_at' => '2025-12-05 00:00:00',
                'notes' => 'Pre-seeded: Historical data'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_pm_generated_invoice_months');
    }
};
