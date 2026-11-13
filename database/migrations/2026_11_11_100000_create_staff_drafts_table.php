<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // A part-finished Add Staff form, kept on the server so it can be reopened from any computer. The form's fields and the
    // names of its saved files are in "payload", which is encrypted (it holds ID numbers); the files themselves are on the
    // private disk under staff_drafts/{id}.
    public function up(): void
    {
        if (Schema::hasTable('staff_drafts')) {
            return;
        }

        Schema::create('staff_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->index();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('title', 255)->nullable();
            $table->longText('payload');
            $table->string('last_tab', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_drafts');
    }
};
