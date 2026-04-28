<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_fin_cheque_books_261_repeat', function (Blueprint $table) {
            $table->unsignedBigInteger('locked_by_id')->nullable()->after('current_status_id');
            $table->timestamp('locked_at')->nullable()->after('locked_by_id');
            $table->string('lock_token', 100)->nullable()->after('locked_at');
            $table->unsignedBigInteger('used_by_id')->nullable()->after('lock_token');
            $table->timestamp('used_at')->nullable()->after('used_by_id');
            $table->string('used_for', 255)->nullable()->after('used_at');
            $table->unsignedBigInteger('cancelled_by_id')->nullable()->after('used_for');
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by_id');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');
            $table->timestamps();

            $table->index(['parent_id', 'current_status_id'], 'idx_leaves_book_status');
        });
    }

    public function down(): void
    {
        Schema::table('sys_fin_cheque_books_261_repeat', function (Blueprint $table) {
            $table->dropIndex('idx_leaves_book_status');
            $table->dropColumn([
                'locked_by_id', 'locked_at', 'lock_token',
                'used_by_id', 'used_at', 'used_for',
                'cancelled_by_id', 'cancelled_at', 'cancel_reason',
                'created_at', 'updated_at',
            ]);
        });
    }
};
