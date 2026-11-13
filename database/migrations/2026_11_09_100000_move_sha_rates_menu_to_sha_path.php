<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const OLD_URL = '/hrsettings/shif/rates';
    private const NEW_URL = '/hrsettings/sha/rates';

    // The SHA Rates page moved from /hrsettings/shif/rates to /hrsettings/sha/rates. Which roles may open a page is decided
    // by its address in cb_sub_modules, so the menu row moves with it. Without this the menu link is a 404 and the new
    // address is not in the table, which leaves it open to every role.
    public function up(): void
    {
        $this->move(self::OLD_URL, self::NEW_URL);
    }

    public function down(): void
    {
        $this->move(self::NEW_URL, self::OLD_URL);
    }

    private function move(string $from, string $to): void
    {
        if (!Schema::hasTable('cb_sub_modules') || DB::table('cb_sub_modules')->where('sub_module_url', $to)->exists()) {
            return;
        }

        DB::table('cb_sub_modules')->where('sub_module_url', $from)->update(['sub_module_url' => $to]);
    }
};
