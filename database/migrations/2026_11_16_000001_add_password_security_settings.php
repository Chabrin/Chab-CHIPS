<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows shown on System Controls under "Password Security": key, label, default value, switch or text box.
     */
    private const SETTINGS = [
        ['password_security_enabled', 'Enforce password security', '0', 1],
        ['password_expiry_days', 'Password expires after (days)', '30', 0],
        ['password_reminder_days', 'Remind before expiry (days, comma separated)', '3,1', 0],
        ['password_min_length', 'Minimum password length', '8', 0],
        ['password_require_complexity', 'Require upper case, lower case and a number', '1', 1],
        ['password_history_count', 'Recent passwords that cannot be reused (0 = all)', '0', 0],
        ['password_block_similar', 'Block a password similar to the current one', '1', 1],
    ];

    /**
     * Add the Password Security category and its settings. Password security starts switched off.
     * Rows that already exist are left as they are, so running this again never overwrites a changed value.
     */
    public function up(): void
    {
        if (!Schema::hasTable('control_categories') || !Schema::hasTable('cb_system_configs')) {
            return;
        }

        $categoryId = DB::table('control_categories')->where('category_name', 'password_security')->value('id');
        if (!$categoryId) {
            $categoryId = DB::table('control_categories')->insertGetId([
                'category_name' => 'password_security', 'display_name' => 'Password Security',
                'precedence' => (int) DB::table('control_categories')->max('precedence') + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (self::SETTINGS as $order => [$key, $label, $default, $toggle]) {
            if (DB::table('cb_system_configs')->where('setting', $key)->exists()) {
                continue;
            }
            DB::table('cb_system_configs')->insert([
                'category_id' => $categoryId, 'display_name' => $label, 'precedence' => $order + 1,
                'value' => $default, 'setting' => $key, 'toggle_boolean' => $toggle,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Remove the Password Security settings and category. The rules fall back to their defaults.
     */
    public function down(): void
    {
        if (!Schema::hasTable('control_categories') || !Schema::hasTable('cb_system_configs')) {
            return;
        }

        DB::table('cb_system_configs')->whereIn('setting', array_column(self::SETTINGS, 0))->delete();
        DB::table('control_categories')->where('category_name', 'password_security')->delete();
    }
};
