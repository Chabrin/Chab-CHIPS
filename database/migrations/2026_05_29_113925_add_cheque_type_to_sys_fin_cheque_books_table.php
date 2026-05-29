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
        Schema::table('sys_fin_cheque_books', function (Blueprint $table) {
    
            if (!Schema::hasColumn('sys_fin_cheque_books', 'cheque_type')) {
                $table->string('cheque_type')
                    ->nullable()
                    ->after('notes')
                    ->comment('Type of cheque book: open or closed');
            }
    
            if (!Schema::hasColumn('sys_fin_cheque_books', 'reference_number')) {
                $table->string('reference_number')->unique();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_fin_cheque_books', function (Blueprint $table) {
            $table->dropColumn('cheque_type');
            $table->dropUnique(['reference_number']);
        });
    }
};
