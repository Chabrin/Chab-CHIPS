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
        Schema::create('sys_hr_communication_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name');
            $table->string('template_type'); // contract, leave_application, contract_termination, disciplinary_action
            $table->string('communication_method'); // email, sms
            $table->text('subject')->nullable();
            $table->longText('body');
            $table->json('general_config')->nullable(); // For general communication settings
            $table->string('attachment_path')->nullable();
            $table->integer('current_status_id')->default(1);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('date_time')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::dropIfExists('sys_hr_communication_templates');
    }
};
