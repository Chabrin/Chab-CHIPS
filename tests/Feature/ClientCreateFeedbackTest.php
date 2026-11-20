<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientBankDetail;
use App\Models\ClientDraftRepeater;
use App\Http\Controllers\ClientsController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientCreateFeedbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never use the workstation database for client creation or rollback tests.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->withoutMiddleware();
        Bus::fake();
        Route::post('/__test/client-create-feedback', function (Request $request) {
            $request->setLaravelSession(app('session')->driver());
            return app(ClientsController::class)->store($request);
        });

        foreach ([new Client(), new ClientDraftRepeater(), new ClientBankDetail()] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $column) {
                    $table->string($column)->nullable();
                }
                if ($model->usesTimestamps()) {
                    $table->timestamps();
                }
            });
        }
        Schema::create('sys_set_banks', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
        });
        DB::table('sys_set_banks')->insert(['id' => 1, 'bank_name' => 'Example Bank']);
    }

    private function clientData(array $overrides = []): array
    {
        return array_replace([
            'client_type_id' => 3,
            'company_name' => 'EXAMPLE COMPANY',
            'mobile_number' => '254712345678',
            'draft_token' => 'test-client-draft',
            'select_time' => '2025-06-10T09:30',
        ], $overrides);
    }

    private function stageBank(): void
    {
        ClientDraftRepeater::create([
            'draft_token' => 'test-client-draft', 'kind' => 'bank',
            'bank_id' => 1, 'account_name' => 'EXAMPLE COMPANY', 'account_number' => '00001234',
        ]);
    }

    public function test_invalid_client_returns_field_errors_and_no_success_confirmation(): void
    {
        $response = $this->postJson('/__test/client-create-feedback', $this->clientData(['mobile_number' => '0712345678']));

        $response->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('save_state', 'not_saved')->assertJsonValidationErrors('mobile_number');
        $this->assertSame(0, Client::count());
        $this->assertFalse(session()->has('client_created'));
    }

    public function test_required_repeater_errors_identify_the_right_tabs(): void
    {
        $response = $this->postJson('/__test/client-create-feedback', $this->clientData([
            'client_type_id' => 1, 'first_name' => 'EXAMPLE', 'last_name' => 'CLIENT',
            'gender' => 'Female', 'title' => null, 'passport_number' => 'EXAMPLE-PASSPORT',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['banks', 'next_of_kin']);
        $this->assertSame(0, Client::count());
    }

    public function test_success_confirms_a_committed_client_keeps_the_entered_date_and_attaches_the_bank(): void
    {
        $this->stageBank();
        $response = Client::withoutEvents(fn () => $this->postJson('/__test/client-create-feedback', $this->clientData()));

        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('client.name', 'EXAMPLE COMPANY')->assertJsonPath('client.status', 'Pending approval');
        $client = Client::sole();
        $this->assertSame('2025-06-10 09:30:00', $client->registered_date->format('Y-m-d H:i:s'));
        $this->assertSame('00001234', ClientBankDetail::sole()->account_number);
        $this->assertSame((string) $client->id, (string) ClientBankDetail::sole()->client_id);
        $this->assertSame(0, ClientDraftRepeater::count());
        $this->assertSame($client->reference_number, session('client_created.reference'));
        $this->assertStringContainsString('/property/clients/', $response->json('redirect'));
    }

    public function test_a_failed_linked_record_rolls_back_the_client_and_retains_the_draft(): void
    {
        $this->stageBank();
        Schema::drop('clients_bank_details');
        $response = Client::withoutEvents(fn () => $this->postJson('/__test/client-create-feedback', $this->clientData()));

        $response->assertStatus(500)->assertJsonPath('success', false)->assertJsonPath('save_state', 'not_saved');
        $this->assertSame(0, Client::count());
        $this->assertSame(1, ClientDraftRepeater::count());
        $this->assertFalse(session()->has('client_created'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_bank_form_returns_specific_missing_fields(): void
    {
        $this->postJson('/ajax/clients/draft-repeaters', ['draft_token' => 'test-client-draft', 'kind' => 'bank', 'bank_id' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['account_name', 'account_number']);
        $this->assertSame(0, ClientDraftRepeater::count());
    }

    public function test_bank_entries_can_be_added_read_edited_and_removed_before_client_creation(): void
    {
        $created = $this->postJson('/ajax/clients/draft-repeaters', [
            'draft_token' => 'test-client-draft', 'kind' => 'bank', 'bank_id' => 1,
            'account_name' => 'EXAMPLE COMPANY', 'account_number' => '00001234',
        ])->assertOk()->assertJsonPath('success', true);
        $id = rawurlencode($created->json('data.encrypted_id'));
        $this->getJson('/ajax/clients/draft-repeaters?draft_token=test-client-draft&kind=bank')
            ->assertOk()->assertJsonPath('data.0.account_number', '00001234')->assertJsonPath('data.0.bank_name', 'Example Bank');
        $this->putJson('/ajax/clients/draft-repeaters/'.$id, ['bank_id' => 1, 'account_name' => 'UPDATED NAME', 'account_number' => '00005678'])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('00005678', ClientDraftRepeater::sole()->account_number);
        $this->deleteJson('/ajax/clients/draft-repeaters/'.$id)->assertOk()->assertJsonPath('success', true);
        $this->assertSame(0, ClientDraftRepeater::count());
        $this->assertSame(0, Client::count());
    }
}
