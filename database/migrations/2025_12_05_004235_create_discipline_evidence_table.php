<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            
            $table->enum('evidence_type', [
                'Document', 'Photo', 'Video', 'Audio', 'Physical Item', 'Digital', 'Other'
            ]);
            $table->text('evidence_description');
            $table->string('evidence_source', 255)->nullable()->comment('Where obtained from');
            
            $table->text('chain_of_custody')->nullable();
            $table->boolean('is_original')->default(true);
            
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->foreign('case_id')
                  ->references('id')
                  ->on('discipline_cases')
                  ->onDelete('cascade');
            // $table->foreign('uploaded_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_evidence');
    }
};