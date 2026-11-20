<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The HELB compliance certificate is a mandatory document for every new staff member. The only way round it is an exception
 * recorded by the HR Manager with a written reason. Each staff member also says whether they are a HELB loan beneficiary, so HR is
 * reminded to set up the deduction and declare them to HELB.
 *
 * - document_types gets "HELB Compliance Certificate" (mandatory).
 * - is_helb_beneficiary: yes/no; empty for staff added before the question was asked.
 * - helb_certificate_exception_*: why the certificate was not required, who allowed it and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_types') && !DB::table('document_types')->where('name', 'HELB Compliance Certificate')->exists()) {
            $row = ['name' => 'HELB Compliance Certificate'];
            if (Schema::hasColumn('document_types', 'is_mandatory')) {
                $row['is_mandatory'] = true;
            }
            if (Schema::hasColumn('document_types', 'created_at')) {
                $row['created_at'] = now();
                $row['updated_at'] = now();
            }
            DB::table('document_types')->insert($row);
        }

        Schema::table('sys_staff', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_staff', 'is_helb_beneficiary')) {
                $table->boolean('is_helb_beneficiary')->nullable();
            }
            if (!Schema::hasColumn('sys_staff', 'helb_certificate_exception_reason')) {
                $table->text('helb_certificate_exception_reason')->nullable();
            }
            if (!Schema::hasColumn('sys_staff', 'helb_certificate_exception_by')) {
                $table->unsignedBigInteger('helb_certificate_exception_by')->nullable();
            }
            if (!Schema::hasColumn('sys_staff', 'helb_certificate_exception_at')) {
                $table->timestamp('helb_certificate_exception_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sys_staff', function (Blueprint $table) {
            foreach (['is_helb_beneficiary', 'helb_certificate_exception_reason', 'helb_certificate_exception_by', 'helb_certificate_exception_at'] as $column) {
                if (Schema::hasColumn('sys_staff', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        if (Schema::hasTable('document_types')) {
            DB::table('document_types')->where('name', 'HELB Compliance Certificate')->delete();
        }
    }
};
