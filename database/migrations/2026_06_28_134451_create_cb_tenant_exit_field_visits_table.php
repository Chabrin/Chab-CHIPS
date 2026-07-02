<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cb_tenant_exit_field_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_id')->constrained('cb_tenant_exits')->cascadeOnDelete();
            $table->date('visit_date');
            $table->string('officer_name', 255);
            $table->enum('unit_status_found', ['Occupied', 'Partially Cleared', 'Fully Cleared/Empty']);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('josya_users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('exit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_exit_field_visits');
    }
};
