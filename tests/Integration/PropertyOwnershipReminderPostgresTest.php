<?php

namespace Tests\Integration;

use App\Console\Commands\RemindDuePropertyTransfers;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Native PostgreSQL regression coverage; fixtures only exist in connection-local temporary tables. */
class PropertyOwnershipReminderPostgresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PROPERTY_TRANSFER_TEST_PG') !== '1') {
            $this->markTestSkipped('Set PROPERTY_TRANSFER_TEST_PG=1 to test reminders in isolated local PostgreSQL temporary tables.');
        }
        $env = Dotenv::parse(file_get_contents(base_path('.env')));
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $this->assertContains($host, ['127.0.0.1', 'localhost']);
        config(['database.default' => 'ownership_pg', 'database.connections.ownership_pg' => [
            'driver' => 'pgsql', 'host' => $host, 'port' => $env['DB_PORT'] ?? 5432,
            'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'pg_temp', 'sslmode' => 'prefer',
        ]]);
        DB::purge('ownership_pg');
        DB::beginTransaction();
        DB::statement('CREATE TEMP TABLE ownership_test_anchor (id bigint) ON COMMIT DROP');
        DB::statement('SET LOCAL search_path = pg_temp');
        $this->assertStringStartsWith('pg_temp_', DB::selectOne('SELECT current_schema() AS name')->name);
        foreach ([
            'notifications' => 'id bigserial PRIMARY KEY, type text, notifiable_type text, notifiable_id bigint, data text, title text, message text, action_url text, read_at timestamp, created_at timestamp, updated_at timestamp',
            'property_ownership_transfers' => 'id bigint PRIMARY KEY, property_id bigint, status text, effective_date date',
            'sys_pm_properties' => 'id bigint PRIMARY KEY, client_id bigint, property_name text, reference_number text, active integer',
            'josya_users' => 'id bigint PRIMARY KEY, name text, block boolean',
            'cb_roles' => 'id bigint, name text, display_name text, is_global boolean',
            'cb_role_user' => 'user_id bigint, role_id bigint, user_type text',
            'cb_permissions' => 'id bigint, name text',
            'cb_permission_role' => 'permission_id bigint, role_id bigint',
            'cb_permission_user' => 'permission_id bigint, user_id bigint, user_type text',
            'cb_sub_modules' => 'id bigint, sub_module_url text',
            'cb_sub_module_permissions' => 'role_id bigint, sub_module_id bigint',
        ] as $table => $columns) {
            DB::statement('CREATE TEMP TABLE '.$table.' ('.$columns.') ON COMMIT DROP');
        }
        DB::table('josya_users')->insert(['id' => 1, 'name' => 'Test Director', 'block' => false]);
        DB::table('cb_roles')->insert(['id' => 1, 'name' => 'directors', 'display_name' => 'Directors', 'is_global' => true]);
        DB::table('cb_role_user')->insert(['user_id' => 1, 'role_id' => 1, 'user_type' => User::class]);
        DB::table('cb_permissions')->insert([['id' => 1, 'name' => 'view_property_transfers'], ['id' => 2, 'name' => 'execute_property_transfers']]);
        DB::table('cb_permission_role')->insert([['permission_id' => 1, 'role_id' => 1], ['permission_id' => 2, 'role_id' => 1]]);
        DB::table('cb_sub_modules')->insert(['id' => 1, 'sub_module_url' => '/property/ownership-transfers']);
        DB::table('cb_sub_module_permissions')->insert(['role_id' => 1, 'sub_module_id' => 1]);
        DB::table('sys_pm_properties')->insert(['id' => 1, 'client_id' => 10, 'property_name' => 'Demo property', 'reference_number' => 'DEMO', 'active' => 1]);
        DB::table('property_ownership_transfers')->insert([
            ['id' => 1, 'property_id' => 1, 'status' => 'approved', 'effective_date' => '2026-12-01'],
            ['id' => 2, 'property_id' => 1, 'status' => 'cancelled', 'effective_date' => '2026-12-01'],
        ]);
        $this->travelTo(Carbon::parse('2026-12-01 09:00:00', 'Africa/Nairobi'));
    }

    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() === 'ownership_pg') {
            DB::rollBack();
            DB::disconnect('ownership_pg');
        }
        parent::tearDown();
    }

    public function test_text_json_reminders_are_scoped_deduplicated_and_retired_without_changing_ownership(): void
    {
        $cancelled = Notification::create([
            'type' => RemindDuePropertyTransfers::TYPE, 'notifiable_type' => User::class, 'notifiable_id' => 1,
            'data' => ['transfer_id' => 2],
        ]);
        $otherType = Notification::create([
            'type' => 'OtherNotification', 'notifiable_type' => User::class, 'notifiable_id' => 1,
            'data' => ['transfer_id' => 2],
        ]);

        // Exercises all three TEXT-to-JSON selectors: scoped cleanup, exclusion and duplicate checking.
        $this->artisan('property-transfers:remind-due', ['--transfer' => 1])
            ->expectsOutputToContain('Created 1 in-app reminder(s).')->assertSuccessful();
        $due = Notification::where('type', RemindDuePropertyTransfers::TYPE)->where('id', '!=', $cancelled->id)->sole();
        $this->assertSame(1, $due->data['transfer_id']);
        $this->assertNull($cancelled->fresh()->read_at, 'A targeted run must leave other transfers alone.');

        $due->markAsRead();
        $this->artisan('property-transfers:remind-due', ['--transfer' => 1])
            ->expectsOutputToContain('Created 0 in-app reminder(s).')->assertSuccessful();
        $this->assertSame(3, Notification::count(), 'Reading a notification must not trigger a duplicate.');

        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertNotNull($cancelled->fresh()->read_at);
        $this->assertNull($otherType->fresh()->read_at);
        $this->assertSame('approved', DB::table('property_ownership_transfers')->where('id', 1)->value('status'));
        $this->assertSame(10, DB::table('sys_pm_properties')->where('id', 1)->value('client_id'));
    }

    public function test_preview_does_not_write_and_targeted_cleanup_handles_an_empty_due_list(): void
    {
        $notification = Notification::create([
            'type' => RemindDuePropertyTransfers::TYPE, 'notifiable_type' => User::class, 'notifiable_id' => 1,
            'data' => ['transfer_id' => 2],
        ]);
        $this->artisan('property-transfers:remind-due', ['--dry-run' => true])
            ->expectsOutputToContain('would create 1 in-app reminder(s)')->assertSuccessful();
        $this->assertSame(1, Notification::count());
        $this->assertNull($notification->fresh()->read_at);
        $this->artisan('property-transfers:remind-due', ['--transfer' => 2])->assertSuccessful();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
