<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Path on the private (local) disk to the staff member's own signature image.
    public function up(): void
    {
        if (!Schema::hasColumn('sys_staff', 'signature')) {
            Schema::table('sys_staff', function (Blueprint $table) {
                $table->string('signature')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sys_staff', 'signature')) {
            Schema::table('sys_staff', function (Blueprint $table) {
                $table->dropColumn('signature');
            });
        }
    }
};
