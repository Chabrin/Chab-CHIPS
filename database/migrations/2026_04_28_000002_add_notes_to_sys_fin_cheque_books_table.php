<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_fin_cheque_books', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('current_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('sys_fin_cheque_books', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
