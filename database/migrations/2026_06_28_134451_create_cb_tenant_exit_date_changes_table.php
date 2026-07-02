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
        Schema::create('cb_tenant_exit_date_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_id')->constrained('cb_tenant_exits')->cascadeOnDelete();
            $table->date('previous_date');
            $table->date('new_date');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('josya_users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('exit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_exit_date_changes');
    }
};
