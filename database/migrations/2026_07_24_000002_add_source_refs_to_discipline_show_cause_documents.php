<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discipline_show_cause_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('source_evidence_file_id')->nullable()->after('document_path');
            $table->unsignedBigInteger('source_witness_document_id')->nullable()->after('source_evidence_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('discipline_show_cause_documents', function (Blueprint $table) {
            $table->dropColumn(['source_evidence_file_id', 'source_witness_document_id']);
        });
    }
};
