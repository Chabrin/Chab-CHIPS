<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a staff document goes when someone removes it or replaces it (for example a signed contract uploaded by mistake). The
 * row leaves employee_documents so the rest of the system sees one current copy, but the file stays on disk and this row keeps
 * what it was, who removed it and when, so nothing is ever lost and every removal is on record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_document_removals')) {
            return;
        }

        Schema::create('employee_document_removals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->nullable()->comment('The id the document had in employee_documents');
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->unsignedBigInteger('document_type_id')->nullable();
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->unsignedBigInteger('removed_by')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->string('reason', 255)->nullable()->comment('Removed, or replaced by a newer file');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_document_removals');
    }
};
