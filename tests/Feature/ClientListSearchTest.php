<?php

namespace Tests\Feature;

use App\Http\Controllers\ClientsController;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientListSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // These fixtures must never touch the workstation's property database.
        config(['database.default' => 'client_list_test', 'database.connections.client_list_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('client_list_test');
        $connection = DB::connection();
        $this->assertSame(':memory:', $connection->getDatabaseName());
        // Exercise the real query with SQLite's equivalent case-insensitive LIKE.
        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function whereBasic(Builder $query, $where)
            {
                if (strtolower($where['operator']) === 'ilike') {
                    $where['operator'] = 'like';
                }
                return parent::whereBasic($query, $where);
            }
        });
        Schema::create('sys_pm_clients', function (Blueprint $table) {
            $table->id();
            $table->integer('type_id');
            foreach (['names', 'mobile_number', 'national_id', 'reference_number', 'email_address', 'registered_date'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('cb_tenant_records', fn (Blueprint $table) => $table->id());
        Schema::create('cb_roles', function (Blueprint $table) { $table->id(); $table->boolean('is_global'); });
        Schema::create('cb_role_user', function (Blueprint $table) { $table->integer('user_id'); $table->integer('role_id'); });
        Schema::create('sys_user_staff_links', function (Blueprint $table) { $table->integer('user_id'); $table->integer('staff_id'); });
        DB::table('cb_roles')->insert(['id' => 1, 'is_global' => true]);
        DB::table('cb_role_user')->insert(['user_id' => 99, 'role_id' => 1]);
        $this->actingAs((new User())->forceFill(['id' => 99]));
        Gate::before(fn ($user, $ability) => $ability === 'read_clients');
        DB::table('sys_pm_clients')->insert([
            ['id' => 1, 'type_id' => 1, 'names' => 'MARY EXAMPLE', 'national_id' => '99887766', 'mobile_number' => '254712000101', 'reference_number' => 'LAN-00001', 'email_address' => 'mary@example.test', 'registered_date' => '2025-01-01'],
            ['id' => 2, 'type_id' => 1, 'names' => 'DANIEL EXAMPLE', 'national_id' => '44556677', 'mobile_number' => '254722000102', 'reference_number' => 'LAN-00002', 'email_address' => 'daniel@example.test', 'registered_date' => '2026-02-01'],
            ['id' => 3, 'type_id' => 2, 'names' => 'TENANT EXAMPLE', 'national_id' => '99887766', 'mobile_number' => '254733000103', 'reference_number' => 'TEN-00003', 'email_address' => 'tenant@example.test', 'registered_date' => '2025-03-01'],
        ]);
    }

    private function listing(array $parameters = []): array
    {
        $response = app(ClientsController::class)->getCLients(Request::create('/property/clients/data', 'GET', array_replace([
            'draw' => 4, 'start' => 0, 'length' => 25,
        ], $parameters)));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        return $response->getData(true);
    }

    public function test_main_search_finds_national_id_without_including_tenants(): void
    {
        $result = $this->listing(['search' => ['value' => '99887766']]);
        $this->assertSame([1], array_column($result['data'], 'id'));
        $this->assertSame(2, $result['recordsTotal']);
        $this->assertSame(1, $result['recordsFiltered']);
        $this->assertSame(4, $result['draw']);
        $this->assertTrue($result['data'][0]['can_read']);
        $this->assertFalse($result['data'][0]['can_update']);
    }

    public function test_name_and_id_tokens_can_be_combined(): void
    {
        $result = $this->listing(['search' => ['value' => 'mary 9988']]);
        $this->assertSame([1], array_column($result['data'], 'id'));
        $this->assertSame([], $this->listing(['search' => ['value' => 'daniel 9988']])['data']);
    }

    public function test_phone_reference_and_specific_field_filters_still_work(): void
    {
        $this->assertSame([1], array_column($this->listing(['search' => ['value' => '254712']])['data'], 'id'));
        $this->assertSame([2], array_column($this->listing(['search' => ['value' => 'LAN-00002']])['data'], 'id'));
        $this->assertSame([2], array_column($this->listing(['columns' => [4 => ['search' => ['value' => '445566']]]])['data'], 'id'));
        $this->assertSame([], $this->listing(['search' => ['value' => '99887766'], 'columns' => [2 => ['search' => ['value' => 'DANIEL']]]])['data']);
    }

    public function test_date_sorting_and_paging_keep_the_existing_column_positions(): void
    {
        $result = $this->listing(['order' => [['column' => 6, 'dir' => 'desc']], 'length' => 1]);
        $this->assertSame([2], array_column($result['data'], 'id'));
        $this->assertSame(2, $result['recordsFiltered']);
        $this->assertSame([1], array_column($this->listing(['order' => [['column' => 6, 'dir' => 'desc']], 'length' => 1, 'start' => 1])['data'], 'id'));
    }

    public function test_search_cannot_bypass_a_users_client_access_scope(): void
    {
        $this->actingAs((new User())->forceFill(['id' => 100]));
        $result = $this->listing(['search' => ['value' => '99887766']]);
        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['recordsTotal']);
    }
}
