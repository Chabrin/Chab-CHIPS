<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('temp_upload_batches', fn (Blueprint $table) => $table->string('mode')->default('new_units'));
    }

    public function down(): void
    {
        Schema::table('temp_upload_batches', fn (Blueprint $table) => $table->dropColumn('mode'));
    }
};
