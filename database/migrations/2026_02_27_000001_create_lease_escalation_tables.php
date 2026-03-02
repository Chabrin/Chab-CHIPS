<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_lease_escalations', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_time')->nullable();
            $table->string('reference_number', 50)->comment('Unique reference for the escalation, e.g. ESC-20240227-001');
            $table->unsignedInteger('client_id')->nullable()->comment('Landlord FK to sys_pm_clients');
            $table->unsignedInteger('property_id')->nullable()->comment('FK to sys_pm_properties');
            $table->unsignedInteger('unit_id')->nullable()->comment('Null = property-level escalation');
            $table->unsignedInteger('lease_agreement_id')->nullable()->comment('FK to sys_pm_lease_agreements');
            $table->string('scope', 20)->default('unit')->comment('property or unit');
            $table->string('escalation_type', 20)->comment('percentage or flat_rate');
            $table->decimal('escalation_value', 13, 2)->comment('Percentage value or flat amount');
            $table->decimal('previous_amount', 13, 2)->nullable()->comment('Rent amount before escalation');
            $table->decimal('new_amount', 13, 2)->nullable()->comment('Calculated new rent amount');
            $table->date('effective_date')->nullable();
            $table->string('status', 30)->default('pending_zm')->comment('pending_zm, pending_pm, approved, rejected');
            $table->unsignedInteger('created_by_id')->nullable()->comment('FO user who created');
            $table->text('notes')->nullable();
            $table->unsignedInteger('current_status_id')->nullable();

            $table->index('client_id');
            $table->index('property_id');
            $table->index('status');
            $table->index('created_by_id');
        });

        Schema::create('cb_lease_escalation_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('escalation_id');
            $table->tinyInteger('approval_step')->comment('1=FO submit, 2=ZM confirm, 3=PM confirm');
            $table->unsignedInteger('user_id')->nullable()->comment('FK to josya_users');
            $table->string('action', 20)->comment('submitted, approved, rejected');
            $table->text('comments')->nullable();
            $table->string('documents', 500)->nullable()->comment('File path for uploaded proof');
            $table->dateTime('date_time')->nullable();

            $table->foreign('escalation_id')
                ->references('id')
                ->on('cb_lease_escalations')
                ->onDelete('cascade');

            $table->index('escalation_id');
            $table->index('approval_step');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_lease_escalation_approvals');
        Schema::dropIfExists('cb_lease_escalations');
    }
};
