<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_history_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->enum('update_type', [
                'Warning Added', 'Sanction Recorded', 'Performance Review Flag', 
                'Promotion Block', 'Record Expunged'
            ]);
            
            $table->text('details')->nullable();
            $table->date('expiry_date')->nullable()->comment('When warning expires');
            
            $table->unsignedBigInteger('updated_by');
            $table->timestamp('updated_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_history_updates');
    }
};
