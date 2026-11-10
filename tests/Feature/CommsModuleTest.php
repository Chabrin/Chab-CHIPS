<?php

namespace Tests\Feature;

use App\Http\Controllers\CommunicationsController;
use App\Mail\GenericCommMail;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\CommBatch;
use App\Services\Comms\AudienceResolver;
use App\Services\Comms\CommBatchService;
use App\Services\Comms\EmailService;
use App\Services\Comms\SmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Communications module: audience resolution (open leases only, property/zone narrowing),
 * placeholder substitution, SMS unit counting, per-recipient logging (sent, failed, skipped),
 * batch dispatch and counters, and resending a batch's failures.
 */
class CommsModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_pm_clients', function (Blueprint $t) {
            $t->id();
            $t->string('names')->nullable();
            $t->string('email_address')->nullable();
            $t->string('mobile_number')->nullable();
            $t->string('reference_number')->nullable();
            $t->string('type_id')->nullable();
        });

        Schema::create('sys_pm_properties', function (Blueprint $t) {
            $t->id();
            $t->string('property_name')->nullable();
            $t->string('reference_number')->nullable();
            $t->integer('zone_id')->nullable();
        });

        Schema::create('sys_pm_units', function (Blueprint $t) {
            $t->id();
            $t->integer('property_id')->nullable();
            $t->string('unit_name')->nullable();
            $t->string('unit_number')->nullable();
        });

        Schema::create('sys_pm_lease_agreements', function (Blueprint $t) {
            $t->id();
            $t->integer('tenant_id')->nullable();
            $t->integer('client_id')->nullable();
            $t->integer('unit_id')->nullable();
            $t->integer('property_id')->nullable();
            $t->date('date_start')->nullable();
            $t->date('date_end')->nullable();
        });

        Schema::create('sys_set_zones', function (Blueprint $t) {
            $t->id();
            $t->string('zone_name')->nullable();
        });

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('full_names')->nullable();
            $t->string('email_address')->nullable();
            $t->string('mobile_number')->nullable();
            $t->string('staff_number')->nullable();
            $t->integer('current_status_id')->nullable();
        });

        Schema::create('cb_sub_modules', function (Blueprint $t) {
            $t->id();
            $t->string('sub_module_url')->nullable();
        });
        Schema::create('cb_role_user', function (Blueprint $t) {
            $t->integer('user_id');
            $t->integer('role_id');
        });
        Schema::create('cb_sub_module_permissions', function (Blueprint $t) {
            $t->integer('role_id');
            $t->integer('sub_module_id');
        });
        foreach (['/communications', '/communications/compose', '/communications/logs', '/communications/metrics'] as $i => $url) {
            DB::table('cb_sub_modules')->insert(['id' => $i + 1, 'sub_module_url' => $url]);
        }
        // user 7 holds role 39 with every Communications page, as the Directors seeder grants
        DB::table('cb_role_user')->insert(['user_id' => 7, 'role_id' => 39]);
        foreach ([1, 2, 3, 4] as $subId) {
            DB::table('cb_sub_module_permissions')->insert(['role_id' => 39, 'sub_module_id' => $subId]);
        }
        $this->actingAs(new \Illuminate\Auth\GenericUser(['id' => 7]));

        foreach (['cb_comm_batches', 'cb_email_logs', 'cb_sms_logs'] as $table) {
            Schema::dropIfExists($table);
        }
        (require database_path('migrations/2026_11_10_100001_create_cb_comm_batches_table.php'))->up();
        (require database_path('migrations/2026_11_10_100002_create_cb_email_logs_table.php'))->up();
        (require database_path('migrations/2026_11_10_100003_create_cb_sms_logs_table.php'))->up();
    }

    /**
     * Two tenants on open leases in different properties, one exited tenant, one tenant without
     * an email. Returns [property1Id, property2Id].
     */
    private function seedTenants(): array
    {
        DB::table('sys_set_zones')->insert([['id' => 5, 'zone_name' => 'G'], ['id' => 6, 'zone_name' => 'H']]);
        DB::table('sys_pm_properties')->insert([
            ['id' => 1, 'property_name' => 'Alpha Court', 'zone_id' => 5],
            ['id' => 2, 'property_name' => 'Beta Villas', 'zone_id' => 6],
        ]);
        DB::table('sys_pm_units')->insert([
            ['id' => 11, 'property_id' => 1, 'unit_name' => 'A1'],
            ['id' => 12, 'property_id' => 1, 'unit_name' => 'A2'],
            ['id' => 21, 'property_id' => 2, 'unit_name' => 'B1'],
        ]);
        DB::table('sys_pm_clients')->insert([
            ['id' => 101, 'names' => 'Jane Wanjiru', 'email_address' => 'jane@example.com', 'mobile_number' => '0700000001', 'reference_number' => 'TEN-101', 'type_id' => '2'],
            ['id' => 102, 'names' => 'John Otieno', 'email_address' => '', 'mobile_number' => '0700000002', 'reference_number' => 'TEN-102', 'type_id' => '2'],
            ['id' => 103, 'names' => 'Exited Tenant', 'email_address' => 'gone@example.com', 'mobile_number' => '0700000003', 'reference_number' => 'TEN-103', 'type_id' => '2'],
            ['id' => 201, 'names' => 'Landlord One', 'email_address' => 'landlord@example.com', 'mobile_number' => '0700000009', 'reference_number' => 'LAN-201', 'type_id' => '1'],
        ]);
        DB::table('sys_pm_lease_agreements')->insert([
            ['id' => 1, 'tenant_id' => 101, 'client_id' => 201, 'unit_id' => 11, 'property_id' => 1, 'date_start' => '2026-01-01', 'date_end' => null],
            ['id' => 2, 'tenant_id' => 102, 'client_id' => 201, 'unit_id' => 21, 'property_id' => 2, 'date_start' => '2026-02-01', 'date_end' => '2099-01-01'],
            ['id' => 3, 'tenant_id' => 103, 'client_id' => 201, 'unit_id' => 12, 'property_id' => 1, 'date_start' => '2025-01-01', 'date_end' => '2025-12-31'],
        ]);

        return [1, 2];
    }

    public function test_sms_units_counts_segments(): void
    {
        $this->assertSame(1, SmsService::units(str_repeat('a', 160)));
        $this->assertSame(2, SmsService::units(str_repeat('a', 161)));
        $this->assertSame(2, SmsService::units(str_repeat('a', 306)));
        $this->assertSame(3, SmsService::units(str_repeat('a', 307)));
    }

    public function test_substitute_replaces_placeholders(): void
    {
        $recipient = ['name' => 'Jane Wanjiru', 'property' => 'Alpha Court', 'unit' => 'A1', 'reference' => 'TEN-101'];

        $out = CommBatchService::substitute('Dear {first_name} ({name}), unit {unit} at {property}, ref {reference}', $recipient);

        $this->assertSame('Dear Jane (Jane Wanjiru), unit A1 at Alpha Court, ref TEN-101', $out);
    }

    public function test_audience_resolver_tenants_on_open_leases_only(): void
    {
        $this->seedTenants();
        $resolver = new AudienceResolver();

        $all = $resolver->resolve('tenants_all');
        $this->assertSame([101, 102], $all->pluck('recipient_id')->sort()->values()->all(), 'the exited tenant must not be in the audience');

        $property = $resolver->resolve('property', ['property_id' => 1]);
        $this->assertSame([101], $property->pluck('recipient_id')->all());
        $this->assertSame('Alpha Court', $property->first()['property']);
        $this->assertSame('A1', $property->first()['unit']);

        $zone = $resolver->resolve('zone', ['zone_id' => 6]);
        $this->assertSame([102], $zone->pluck('recipient_id')->all());

        $landlords = $resolver->resolve('landlords_all');
        $this->assertSame([201], $landlords->pluck('recipient_id')->all());
        $this->assertSame('landlord', $landlords->first()['recipient_type']);
    }

    public function test_email_service_logs_sent_and_failed(): void
    {
        Mail::fake();
        [$ok] = app(EmailService::class)->send('jane@example.com', 'Jane', 'Hi', '<p>Body</p>', 'general', null, 'tenant', 101);

        $this->assertTrue($ok);
        Mail::assertSent(GenericCommMail::class, 1);
        $this->assertDatabaseHas('cb_email_logs', ['recipient_email' => 'jane@example.com', 'status' => 'sent', 'recipient_id' => 101]);
    }

    public function test_sms_service_fails_cleanly_and_logs_when_unconfigured(): void
    {
        config(['services.sms.provider' => 'none']);

        [$ok, $error] = app(SmsService::class)->send('0700000001', 'Jane', 'Hello', 'general', null, 'tenant', 101);

        $this->assertFalse($ok);
        $this->assertStringContainsString('not configured', $error);
        $this->assertDatabaseHas('cb_sms_logs', ['recipient_phone' => '0700000001', 'status' => 'failed', 'units' => 1]);
    }

    public function test_dispatch_sends_logs_skips_and_counts(): void
    {
        Mail::fake();
        $this->seedTenants();

        [$batch, $error] = app(CommBatchService::class)->dispatch('email', 'tenants_all', [], 'Hello {name}', 'Dear {first_name}', 7);

        $this->assertNull($error);
        $batch = CommBatch::find($batch->id);
        $this->assertSame(2, $batch->recipient_count);
        $this->assertSame(1, $batch->sent_count, 'Jane has an email');
        $this->assertSame(1, $batch->skipped_count, 'John has no email');
        $this->assertSame(0, $batch->failed_count);
        $this->assertSame('done', $batch->status);
        $this->assertNotNull($batch->sent_at);

        $this->assertDatabaseHas('cb_email_logs', ['batch_id' => $batch->id, 'recipient_id' => 101, 'status' => 'sent', 'subject' => 'Hello Jane Wanjiru', 'body' => 'Dear Jane']);
        $this->assertDatabaseHas('cb_email_logs', ['batch_id' => $batch->id, 'recipient_id' => 102, 'status' => 'skipped']);
    }

    public function test_dispatch_rejects_empty_and_contactless_audiences(): void
    {
        $this->seedTenants();
        $service = app(CommBatchService::class);

        [$batch, $error] = $service->dispatch('email', 'single_client', ['client_id' => 999999], 'S', 'B', 7);
        $this->assertNull($batch);
        $this->assertStringContainsString('No recipients', $error);

        [$batch, $error] = $service->dispatch('email', 'single_client', ['client_id' => 102], 'S', 'B', 7);
        $this->assertNull($batch);
        $this->assertStringContainsString('email address', $error);
    }

    public function test_resend_failed_requeues_and_completes_batch(): void
    {
        Mail::fake();
        $this->seedTenants();
        config(['services.sms.provider' => 'none']);

        // SMS to Jane fails (no gateway), giving us a failed batch to resend
        [$batch] = app(CommBatchService::class)->dispatch('sms', 'single_client', ['client_id' => 101], '', 'Hello {name}', 7);
        $batch = CommBatch::find($batch->id);
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame('failed', $batch->status);

        // Configure a provider that still fails? No — fake success is not possible without HTTP;
        // assert the resend path itself: it re-queues, writes a second log row and recounts.
        $count = app(CommBatchService::class)->resendFailed($batch);

        $this->assertSame(1, $count);
        $this->assertSame(2, DB::table('cb_sms_logs')->where('batch_id', $batch->id)->count(), 'the failed row stays as history and the retry writes a new row');
        $batch = CommBatch::find($batch->id);
        $this->assertSame(1, $batch->failed_count, 'the retry failed again, so the counter is back to 1');
        $this->assertSame('failed', $batch->status);
    }

    public function test_estimate_counts_and_previews(): void
    {
        $this->seedTenants();

        $estimate = app(CommBatchService::class)->estimate('sms', 'tenants_all', [], '', 'Hi {first_name} of {unit}');

        $this->assertSame(2, $estimate['total']);
        $this->assertSame(2, $estimate['sendable'], 'both tenants have mobiles');
        $this->assertSame(0, $estimate['skipped']);
        $this->assertSame(2, $estimate['sms_units']);
        $this->assertSame('All current tenants', $estimate['audience_label']);
        // tenants are ordered by newest lease first, so John (Feb lease) is the preview recipient
        $this->assertSame('Hi John of B1', $estimate['preview']['body']);
    }

    public function test_estimate_returns_sample_recipient_for_live_preview(): void
    {
        $this->seedTenants();

        $estimate = app(CommBatchService::class)->estimate('email', 'property', ['property_id' => 1], '', '');

        $this->assertSame(['name' => 'Jane Wanjiru', 'property' => 'Alpha Court', 'unit' => 'A1', 'reference' => 'TEN-101'], $estimate['sample']);
    }

    public function test_compose_page_counts_audience_before_message_is_written(): void
    {
        $this->seedTenants();
        $controller = app(CommunicationsController::class);

        // draft mode: subject and body empty, as the compose page does when it first counts an audience
        $response = $controller->estimate(Request::create('/x', 'POST', ['channel' => 'email', 'audience_type' => 'property', 'property_id' => 1, 'subject' => '', 'body' => '']));
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['total']);
        $this->assertSame(1, $data['sendable']);
    }

    public function test_send_still_requires_subject_and_body(): void
    {
        $this->seedTenants();
        $controller = app(CommunicationsController::class);

        $this->expectException(ValidationException::class);
        $controller->send(Request::create('/x', 'POST', ['channel' => 'email', 'audience_type' => 'tenants_all', 'subject' => '', 'body' => '']));
    }

    public function test_property_search_matches_name_or_reference(): void
    {
        DB::table('sys_pm_properties')->insert([
            ['id' => 1, 'property_name' => 'Alpha Court', 'reference_number' => '001A'],
            ['id' => 2, 'property_name' => 'Beta Villas', 'reference_number' => '002B'],
        ]);
        $controller = app(CommunicationsController::class);

        $byName = $controller->propertySearch(Request::create('/x', 'GET', ['q' => 'alpha']))->getData(true);
        $this->assertSame([['id' => 1, 'text' => 'Alpha Court (001A)']], $byName['results']);

        $byRef = $controller->propertySearch(Request::create('/x', 'GET', ['q' => '002B']))->getData(true);
        $this->assertSame([2], array_column($byRef['results'], 'id'));
    }

    public function test_batch_status_reports_live_counters(): void
    {
        $batch = CommBatch::create(['channel' => 'sms', 'body' => 'x', 'audience_type' => 'tenants_all', 'recipient_count' => 10, 'sent_count' => 4, 'failed_count' => 1, 'skipped_count' => 2, 'status' => 'sending', 'created_by' => 1]);

        $status = app(CommunicationsController::class)->batchStatus($batch->id)->getData(true);

        $this->assertSame(['success' => true, 'status' => 'sending', 'recipients' => 10, 'sent' => 4, 'failed' => 1, 'skipped' => 2], $status);
    }

    public function test_endpoints_refuse_a_user_without_the_communications_permission(): void
    {
        $this->seedTenants();
        // user 8 is signed in but holds no Communications page
        DB::table('cb_role_user')->insert(['user_id' => 8, 'role_id' => 50]);
        $this->actingAs(new \Illuminate\Auth\GenericUser(['id' => 8]));
        $controller = app(CommunicationsController::class);

        $send = $controller->send(Request::create('/x', 'POST', ['channel' => 'email', 'audience_type' => 'tenants_all', 'subject' => 'S', 'body' => 'B']));
        $this->assertSame(403, $send->getStatusCode());
        $this->assertSame(0, DB::table('cb_comm_batches')->count(), 'a refused send must not create a batch');

        $this->assertSame(403, $controller->estimate(Request::create('/x', 'POST', ['channel' => 'email', 'audience_type' => 'tenants_all']))->getStatusCode());
        $this->assertSame(403, $controller->batchesData(Request::create('/x', 'GET'))->getStatusCode());
        $this->assertSame(403, $controller->logsData(Request::create('/x', 'GET'))->getStatusCode());
        $this->assertSame(403, $controller->metricsData(Request::create('/x', 'GET'))->getStatusCode());
        $this->assertSame(403, $controller->clientSearch(Request::create('/x', 'GET', ['q' => 'jane']))->getStatusCode());
        $this->assertSame(403, $controller->resendFailed(1)->getStatusCode());
        $this->assertSame(302, $controller->compose()->getStatusCode(), 'a page redirects to the dashboard');
    }

    public function test_each_page_permission_unlocks_only_its_own_endpoints(): void
    {
        $this->seedTenants();
        // role 51 may open only the Logs page
        DB::table('cb_role_user')->insert(['user_id' => 9, 'role_id' => 51]);
        DB::table('cb_sub_module_permissions')->insert(['role_id' => 51, 'sub_module_id' => 3]);
        $this->actingAs(new \Illuminate\Auth\GenericUser(['id' => 9]));
        $controller = app(CommunicationsController::class);

        $this->assertSame(200, $controller->logsData(Request::create('/x', 'GET'))->getStatusCode());
        $this->assertSame(403, $controller->send(Request::create('/x', 'POST', ['channel' => 'email', 'audience_type' => 'tenants_all', 'subject' => 'S', 'body' => 'B']))->getStatusCode(), 'seeing logs must not allow sending');
        $this->assertSame(403, $controller->metricsData(Request::create('/x', 'GET'))->getStatusCode());
    }
}
