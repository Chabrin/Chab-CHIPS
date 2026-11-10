<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings for the Communications module, one row per kind (mail, sms). The JSON holds host,
     * port, from-address and so on; the secrets inside it (mail password, SMS API key) are stored
     * encrypted with the app key and are never shown back on screen.
     */
    public function up(): void
    {
        if (Schema::hasTable('cb_comm_settings')) {
            return;
        }

        Schema::create('cb_comm_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 30)->unique(); // mail | sms
            $table->json('value_json')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_comm_settings');
    }
};
