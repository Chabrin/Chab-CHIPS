<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_lease_agreements', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_template_id')->nullable()->after('linked_lease_id');
            $table->string('unsigned_pdf_path')->nullable()->after('contract_template_id');
            $table->string('signed_pdf_path')->nullable()->after('unsigned_pdf_path');
            $table->string('contract_sign_token', 64)->nullable()->unique()->after('signed_pdf_path');
            $table->string('signer_name')->nullable()->after('contract_sign_token');
            $table->timestamp('contract_sent_at')->nullable()->after('signer_name');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_lease_agreements', function (Blueprint $table) {
            $table->dropColumn([
                'contract_template_id',
                'unsigned_pdf_path',
                'signed_pdf_path',
                'contract_sign_token',
                'signer_name',
                'contract_sent_at',
                'contract_signed_at',
            ]);
        });
    }
};
