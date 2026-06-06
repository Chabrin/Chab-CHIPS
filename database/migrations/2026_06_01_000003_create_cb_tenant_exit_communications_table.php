<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_tenant_exit_communications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('exit_id');
            $table->unsignedBigInteger('tenant_id');

            $table->enum('comm_type', ['Email', 'SMS', 'Both']);
            $table->string('subject', 255)->nullable();
            $table->text('message_body');

            $table->string('email_address', 255)->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->string('template_used', 100)->nullable();

            $table->boolean('sent_email')->default(false);
            $table->boolean('sent_sms')->default(false);
            $table->json('send_errors')->nullable();

            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->foreign('exit_id')->references('id')->on('cb_tenant_exits')->onDelete('cascade');
            $table->foreign('sent_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('exit_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_exit_communications');
    }
};
