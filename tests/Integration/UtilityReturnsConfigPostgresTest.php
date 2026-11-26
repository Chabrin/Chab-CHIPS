<?php

namespace Tests\Integration;

use App\Http\Controllers\ClientsController;
use App\Models\Client;
use App\Services\UtilityReturnsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresReturnFixtures;
use Tests\TestCase;

class UtilityReturnsConfigPostgresTest extends TestCase
{
    use PostgresReturnFixtures;

    public function test_all_three_modes_survive_fresh_client_loads_and_keep_other_landlords_unchanged(): void
    {
        DB::statement('ALTER TABLE sys_pm_clients ADD COLUMN names text, ADD COLUMN type_id integer');
        DB::table('sys_pm_clients')->where('id', 1)->update(['names' => 'Test Landlord', 'type_id' => 1, 'utility_returns' => 'with_rent']);
        DB::table('sys_pm_clients')->where('id', 2)->update(['names' => 'Other Landlord', 'type_id' => 1, 'utility_returns' => 'with_rent']);
        DB::table('sys_pm_clients')->insert(['id' => 3, 'names' => '  Test   Landlord  ', 'type_id' => 1, 'utility_returns' => 'with_rent']);
        DB::table('sys_pm_clients')->insert(['id' => 4, 'names' => 'Test Landlord', 'type_id' => 2, 'utility_returns' => 'with_rent']);
        $controller = app(ClientsController::class);
        $service = app(UtilityReturnsService::class);

        foreach (['separate_report', 'separate_return', 'with_rent'] as $mode) {
            $response = $controller->saveUtilityReturns(Request::create('/test', 'POST', ['utility_returns' => $mode]), Crypt::encrypt(1));
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($mode, $response->getData(true)['utility_returns']);
            $this->assertSame($mode, Client::findOrFail(1)->utility_returns);
            $this->assertSame($mode, $service->modeForClient(1));
            $this->assertSame($mode, $service->modeForClient(3));
            $this->assertSame('with_rent', $service->modeForClient(2));
            $this->assertSame('with_rent', $service->modeForClient(4));
        }
    }
}
