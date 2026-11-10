<?php

namespace Tests\Feature;

use App\Http\Controllers\CommunicationsController;
use App\Mail\GenericCommMail;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\CommBatch;
use App\Services\Comms\AudienceResolver;
use App\Models\CommSetting;
use App\Services\Comms\CommBatchService;
use App\Services\Comms\CommSettings;
use App\Services\Comms\MailConfigService;
use App\Services\Comms\EmailService;
use App\Services\Comms\SmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        // the test connection prefixes table names; raw SQL with aliases (c.names) would not match, and production has no prefix
        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        Schema::clearResolvedInstance('db.schema');
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_pm_clients', function (Blueprint $t) {
            $t->id();
            $t->string('names')->nullable();
            $t->string('email_address')->nullable();
            $t->string('mobile_number')->nullable();
            $t->string('reference_number')->nullable();
            $t->string('type_id')->nullable();
        });

        Schema::create('cb_tenant_records', function (Blueprint $t) {
            $t->integer('id')->primary();
            $t->string('names')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
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
            $t->integer('occupancy_status_id')->nullable();
        });

        Schema::create('sys_pm_lease_agreements', function (Blueprint $t) {
            $t->id();
            $t->integer('tenant_id')->nullable();
            $t->integer('client_id')->nullable();
            $t->integer('unit_id')->nullable();
            $t->integer('property_id')->nullable();
            $t->date('date_start')->nullable();
            $t->date('date_end')->nullable();
            $t->string('lease_status')->nullable();
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

        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
        });
        DB::table('josya_users')->insert(['id' => 7, 'name' => 'Test Director']);

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
        foreach (['/communications', '/communications/compose', '/communications/logs', '/communications/metrics', '/communications/settings'] as $i => $url) {
            DB::table('cb_sub_modules')->insert(['id' => $i + 1, 'sub_module_url' => $url]);
        }
        // user 7 holds role 39 with every Communications page, as the Directors seeder grants
        DB::table('cb_role_user')->insert(['user_id' => 7, 'role_id' => 39]);
        foreach ([1, 2, 3, 4, 5] as $subId) {
            DB::table('cb_sub_module_permissions')->insert(['role_id' => 39, 'sub_module_id' => $subId]);
        }
        $this->actingAs(new \Illuminate\Auth\GenericUser(['id' => 7, 'email' => 'director@example.com']));

        foreach (['cb_comm_batches', 'cb_email_logs', 'cb_sms_logs', 'cb_comm_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        (require database_path('migrations/2026_11_10_100001_create_cb_comm_batches_table.php'))->up();
        (require database_path('migrations/2026_11_10_100002_create_cb_email_logs_table.php'))->up();
        (require database_path('migrations/2026_11_10_100003_create_cb_sms_logs_table.php'))->up();
        (require database_path('migrations/2026_11_10_100004_create_cb_comm_settings_table.php'))->up();
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
            ['id' => 11, 'property_id' => 1, 'unit_name' => 'A1', 'occupancy_status_id' => 1],
            ['id' => 12, 'property_id' => 1, 'unit_name' => 'A2', 'occupancy_status_id' => 3],
            ['id' => 21, 'property_id' => 2, 'unit_name' => 'B1', 'occupancy_status_id' => 1],
        ]);
        DB::table('sys_pm_clients')->insert([
            ['id' => 101, 'names' => 'Jane Wanjiru', 'email_address' => 'jane@example.com', 'mobile_number' => '0700000001', 'reference_number' => 'TEN-101', 'type_id' => '2'],
            ['id' => 102, 'names' => 'John Otieno', 'email_address' => '', 'mobile_number' => '0700000002', 'reference_number' => 'TEN-102', 'type_id' => '2'],
            ['id' => 103, 'names' => 'Exited Tenant', 'email_address' => 'gone@example.com', 'mobile_number' => '0700000003', 'reference_number' => 'TEN-103', 'type_id' => '2'],
            ['id' => 201, 'names' => 'Landlord One', 'email_address' => 'landlord@example.com', 'mobile_number' => '0700000009', 'reference_number' => 'LAN-201', 'type_id' => '1'],
        ]);
        DB::table('sys_pm_lease_agreements')->insert([
            ['id' => 1, 'tenant_id' => 101, 'client_id' => 201, 'unit_id' => 11, 'property_id' => 1, 'date_start' => '2026-01-01', 'date_end' => null, 'lease_status' => 'Active'],
            ['id' => 2, 'tenant_id' => 102, 'client_id' => 201, 'unit_id' => 21, 'property_id' => 2, 'date_start' => '2026-02-01', 'date_end' => '2099-01-01', 'lease_status' => 'Active'],
            ['id' => 3, 'tenant_id' => 103, 'client_id' => 201, 'unit_id' => 12, 'property_id' => 1, 'date_start' => '2025-01-01', 'date_end' => '2025-12-31', 'lease_status' => 'Terminated'],
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

        $this->assertSame(['name' => 'Jane Wanjiru', 'contact' => 'jane@example.com', 'property' => 'Alpha Court', 'unit' => 'A1', 'reference' => 'TEN-101'], $estimate['sample']);

        $sms = app(CommBatchService::class)->estimate('sms', 'property', ['property_id' => 1], '', '');
        $this->assertSame('0700000001', $sms['sample']['contact'], 'an SMS sample carries the phone number instead');
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

    private function saveMail(array $over = []): void
    {
        app(CommSettings::class)->save('mail', $over + ['host' => 'smtp.example.com', 'port' => '465', 'encryption' => 'ssl', 'username' => 'mailer@example.com', 'password' => 'S3cret!pass', 'from_address' => 'noreply@chabrin.example', 'from_name' => 'Chabrin', 'reply_to' => 'help@chabrin.example', 'reply_to_name' => 'Chabrin Accounts'], 7);
    }

    public function test_secrets_are_stored_encrypted_and_blank_keeps_the_saved_one(): void
    {
        $this->saveMail();

        $stored = CommSetting::where('setting_key', 'mail')->first()->value_json;
        $this->assertNotSame('S3cret!pass', $stored['password'], 'the password must not be stored in plain text');
        $this->assertStringNotContainsString('S3cret', json_encode($stored));
        $this->assertSame('S3cret!pass', app(CommSettings::class)->get('mail')['password']);

        // saving again with a blank password keeps the one already stored
        $this->saveMail(['host' => 'smtp2.example.com', 'password' => '']);
        $this->assertSame('smtp2.example.com', app(CommSettings::class)->get('mail')['host']);
        $this->assertSame('S3cret!pass', app(CommSettings::class)->get('mail')['password']);

        // a new password replaces it
        $this->saveMail(['password' => 'N3w!pass']);
        $this->assertSame('N3w!pass', app(CommSettings::class)->get('mail')['password']);
    }

    public function test_view_never_exposes_the_secret(): void
    {
        $this->saveMail();

        $view = app(CommSettings::class)->view('mail');

        $this->assertTrue($view['has_secret']);
        $this->assertArrayNotHasKey('password', $view);
        $this->assertStringNotContainsString('S3cret', json_encode($view));
        $this->assertFalse(app(CommSettings::class)->view('sms')['saved']);
    }

    public function test_unreadable_secret_falls_back_instead_of_breaking_mail(): void
    {
        $this->saveMail();
        $row = CommSetting::where('setting_key', 'mail')->first();
        $row->update(['value_json' => array_merge($row->value_json, ['password' => 'not-a-valid-ciphertext'])]);

        $this->assertNull(app(CommSettings::class)->get('mail'), 'a secret that cannot be decrypted (app key changed) is treated as not saved');
        $this->assertNull(app(MailConfigService::class)->mailerName());
    }

    public function test_unknown_fields_are_dropped_and_clear_forgets_the_settings(): void
    {
        $this->saveMail(['evil' => 'x', 'transport' => 'sendmail']);

        $this->assertArrayNotHasKey('evil', CommSetting::where('setting_key', 'mail')->first()->value_json);

        app(CommSettings::class)->clear('mail', 7);
        $this->assertNull(app(CommSettings::class)->get('mail'));
    }

    public function test_mailer_definition_maps_security_and_port(): void
    {
        $ssl = MailConfigService::definition(['host' => 'h', 'port' => '465', 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p']);
        $this->assertSame('smtps', $ssl['scheme']);
        $this->assertSame(465, $ssl['port']);

        $tls = MailConfigService::definition(['host' => 'h', 'port' => '587', 'encryption' => 'tls', 'username' => '', 'password' => '']);
        $this->assertSame('smtp', $tls['scheme']);
        $this->assertNull($tls['username'], 'a blank username means no authentication');
        $this->assertNull($tls['password']);
    }

    public function test_saved_settings_register_the_comms_mailer_without_touching_global_mail_from(): void
    {
        config(['mail.from.address' => 'server@default.test']);
        $this->saveMail();

        $service = app(MailConfigService::class);
        $this->assertSame('comms', $service->mailerName());
        $this->assertSame('smtp.example.com', config('mail.mailers.comms.host'));
        $this->assertSame('S3cret!pass', config('mail.mailers.comms.password'), 'the decrypted password goes to the mailer, not to a page');
        $this->assertSame(['noreply@chabrin.example', 'Chabrin'], $service->from());
        $this->assertSame('server@default.test', config('mail.from.address'), 'other mail in the same process must keep the server sender');
    }

    public function test_with_nothing_saved_the_default_mailer_is_used(): void
    {
        $service = app(MailConfigService::class);

        $this->assertNull($service->mailerName());
        $this->assertSame([null, null], $service->from());
    }

    public function test_email_goes_out_from_the_saved_sender(): void
    {
        Mail::fake();
        $this->saveMail();

        [$ok] = app(EmailService::class)->send('jane@example.com', 'Jane', 'Hi', '<p>x</p>');

        $this->assertTrue($ok);
        // read the properties directly: Laravel's hasFrom() helper crashes on a mailable that defines envelope()
        Mail::assertSent(GenericCommMail::class, fn ($m) => $m->hasTo('jane@example.com') && ($m->from[0]['address'] ?? null) === 'noreply@chabrin.example' && ($m->from[0]['name'] ?? null) === 'Chabrin' && ($m->replyTo[0]['address'] ?? null) === 'help@chabrin.example' && ($m->replyTo[0]['name'] ?? null) === 'Chabrin Accounts');
    }

    public function test_sender_and_reply_to_reach_the_real_message_headers(): void
    {
        $mailable = new GenericCommMail('Subject', '<p>Body</p>');
        $mailable->from('noreply@chabrin.example', 'Chabrin')->replyTo('help@chabrin.example');

        Mail::mailer('array')->to('jane@example.com')->send($mailable);
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        $this->assertSame('noreply@chabrin.example', $sent->getFrom()[0]->getAddress());
        $this->assertSame('help@chabrin.example', $sent->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString(config('app.company_name'), $sent->getHtmlBody(), 'the branded layout uses the company name, not APP_NAME');
    }

    public function test_sms_uses_the_saved_africas_talking_credentials(): void
    {
        Http::fake(['api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'status' => 'Success', 'messageId' => 'ATXid_42']]]], 201)]);
        app(CommSettings::class)->save('sms', ['provider' => 'africastalking', 'username' => 'chabrin', 'api_key' => 'at-secret-key', 'sender_id' => 'CHABRIN'], 7);

        [$ok, $error] = app(\App\Services\Comms\SmsService::class)->send('0722000000', 'Jane', 'Hello Jane');

        $this->assertTrue($ok, (string) $error);
        Http::assertSent(fn ($request) => $request->hasHeader('apiKey', 'at-secret-key') && $request['username'] === 'chabrin' && $request['from'] === 'CHABRIN' && $request['to'] === '0722000000');
        $this->assertDatabaseHas('cb_sms_logs', ['recipient_phone' => '0722000000', 'status' => 'sent', 'message_id' => 'ATXid_42']);
    }

    public function test_sms_gateway_rejection_is_logged_as_failed(): void
    {
        Http::fake(['api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 403, 'status' => 'InvalidPhoneNumber']]]], 201)]);
        app(CommSettings::class)->save('sms', ['provider' => 'africastalking', 'username' => 'chabrin', 'api_key' => 'k'], 7);

        [$ok, $error] = app(\App\Services\Comms\SmsService::class)->send('123', 'Jane', 'Hi');

        $this->assertFalse($ok);
        $this->assertSame('InvalidPhoneNumber', $error);
        $this->assertDatabaseHas('cb_sms_logs', ['recipient_phone' => '123', 'status' => 'failed', 'error' => 'InvalidPhoneNumber']);
    }

    public function test_settings_endpoints_refuse_users_without_the_settings_permission(): void
    {
        // role 40 may compose and see logs but is not given Settings
        DB::table('cb_role_user')->insert(['user_id' => 10, 'role_id' => 40]);
        foreach ([2, 3] as $subId) {
            DB::table('cb_sub_module_permissions')->insert(['role_id' => 40, 'sub_module_id' => $subId]);
        }
        $this->actingAs(new \Illuminate\Auth\GenericUser(['id' => 10]));
        $controller = app(CommunicationsController::class);

        $this->assertSame(302, $controller->settingsPage()->getStatusCode());
        $this->assertSame(403, $controller->settingsData()->getStatusCode());
        $this->assertSame(403, $controller->saveMailSettings(Request::create('/x', 'POST', ['host' => 'h', 'port' => 25, 'encryption' => 'none', 'from_address' => 'a@b.co']))->getStatusCode());
        $this->assertSame(403, $controller->saveSmsSettings(Request::create('/x', 'POST', ['provider' => 'none']))->getStatusCode());
        $this->assertSame(403, $controller->testMail(Request::create('/x', 'POST', ['to' => 'a@b.co']))->getStatusCode());
        $this->assertSame(403, $controller->testSms(Request::create('/x', 'POST', ['to' => '0722000000']))->getStatusCode());
        $this->assertNull(CommSetting::where('setting_key', 'mail')->first(), 'a refused save must write nothing');
    }

    public function test_settings_page_data_never_contains_the_secrets(): void
    {
        $this->saveMail();
        app(CommSettings::class)->save('sms', ['provider' => 'africastalking', 'username' => 'u', 'api_key' => 'at-secret-key'], 7);

        $json = json_encode(app(CommunicationsController::class)->settingsData()->getData(true));

        $this->assertStringNotContainsString('S3cret', $json);
        $this->assertStringNotContainsString('at-secret-key', $json);
        $this->assertStringNotContainsString(CommSetting::where('setting_key', 'mail')->first()->value_json['password'], $json, 'not even the encrypted value is sent to the browser');
        $this->assertStringContainsString('"has_secret":true', $json);
    }

    public function test_save_mail_validates_and_blank_password_keeps_the_saved_one(): void
    {
        $controller = app(CommunicationsController::class);
        $post = fn (array $d) => Request::create('/x', 'POST', $d + ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'from_address' => 'noreply@chabrin.example']);

        $controller->saveMailSettings($post(['password' => 'first-pass']));
        $controller->saveMailSettings($post(['password' => '', 'from_name' => 'Renamed']));
        $this->assertSame('first-pass', app(CommSettings::class)->get('mail')['password']);
        $this->assertSame('Renamed', app(CommSettings::class)->get('mail')['from_name']);

        foreach ([['host' => 'bad host;rm -rf'], ['port' => 70000], ['encryption' => 'plain'], ['from_address' => 'not-an-email']] as $bad) {
            try {
                $controller->saveMailSettings($post($bad));
                $this->fail('expected a validation error for ' . json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        $controller->saveMailSettings(Request::create('/x', 'POST', ['use_env' => 1]));
        $this->assertNull(app(CommSettings::class)->get('mail'));
    }

    public function test_sms_save_requires_a_key_for_a_real_provider_but_not_when_one_is_saved(): void
    {
        $controller = app(CommunicationsController::class);

        $refused = $controller->saveSmsSettings(Request::create('/x', 'POST', ['provider' => 'africastalking', 'username' => 'chabrin']));
        $this->assertSame(422, $refused->getStatusCode());

        $controller->saveSmsSettings(Request::create('/x', 'POST', ['provider' => 'africastalking', 'username' => 'chabrin', 'api_key' => 'k1']));
        $kept = $controller->saveSmsSettings(Request::create('/x', 'POST', ['provider' => 'africastalking', 'username' => 'chabrin2']));
        $this->assertSame(200, $kept->getStatusCode());
        $this->assertSame('k1', app(CommSettings::class)->get('sms')['api_key']);
    }

    public function test_test_mail_sends_through_the_service_and_is_logged_as_a_test(): void
    {
        Mail::fake();
        $this->saveMail();

        $response = app(CommunicationsController::class)->testMail(Request::create('/x', 'POST', ['to' => 'me@example.com']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('the saved settings', $response->getData(true)['message']);
        Mail::assertSent(GenericCommMail::class, fn ($m) => $m->hasTo('me@example.com'));
        $this->assertDatabaseHas('cb_email_logs', ['recipient_email' => 'me@example.com', 'type' => 'test', 'status' => 'sent']);
    }

    public function test_reply_to_name_is_used_and_falls_back_to_the_sender_name(): void
    {
        $this->saveMail();
        $this->assertSame(['help@chabrin.example', 'Chabrin Accounts'], app(MailConfigService::class)->replyTo());

        // blank reply-to name: replies show the From name, never a bare address
        $this->saveMail(['reply_to_name' => '']);
        $this->assertSame(['help@chabrin.example', 'Chabrin'], app(MailConfigService::class)->replyTo());

        // a reply-to name without an address means no reply-to at all
        $this->saveMail(['reply_to' => '', 'reply_to_name' => 'Orphan']);
        $this->assertSame([null, null], app(MailConfigService::class)->replyTo());
    }

    public function test_save_mail_validates_reply_to_name_length(): void
    {
        $controller = app(CommunicationsController::class);
        $base = ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'from_address' => 'noreply@chabrin.example'];

        $controller->saveMailSettings(Request::create('/x', 'POST', $base + ['reply_to' => 'a@b.co', 'reply_to_name' => 'Accounts Desk']));
        $this->assertSame('Accounts Desk', app(CommSettings::class)->get('mail')['reply_to_name']);

        $this->expectException(ValidationException::class);
        $controller->saveMailSettings(Request::create('/x', 'POST', $base + ['reply_to_name' => str_repeat('x', 121)]));
    }

    private function seedStaff(): void
    {
        DB::table('sys_staff')->insert([
            ['id' => 1, 'full_names' => 'John Doe', 'email_address' => 'john.doe@chabrin.example', 'mobile_number' => '0711000001', 'staff_number' => 'EMP001', 'current_status_id' => 1],
            ['id' => 2, 'full_names' => 'Mary Atieno', 'email_address' => '', 'mobile_number' => '0711000002', 'staff_number' => 'EMP002', 'current_status_id' => 1],
            ['id' => 3, 'full_names' => 'Peter On Leave', 'email_address' => 'peter@chabrin.example', 'mobile_number' => null, 'staff_number' => 'EMP003', 'current_status_id' => 4],
            ['id' => 4, 'full_names' => 'Gone Person', 'email_address' => 'gone@chabrin.example', 'mobile_number' => '0711000004', 'staff_number' => 'EMP004', 'current_status_id' => 3],
        ]);
    }

    public function test_specific_staff_audience_resolves_only_the_picked_people(): void
    {
        $this->seedStaff();
        $resolver = new AudienceResolver();

        $one = $resolver->resolve('staff', ['staff_ids' => [1]]);
        $this->assertSame([1], $one->pluck('recipient_id')->all());
        $this->assertSame('staff', $one->first()['recipient_type']);
        $this->assertSame('john.doe@chabrin.example', $one->first()['email']);
        $this->assertSame('EMP001', $one->first()['reference']);

        $two = $resolver->resolve('staff', ['staff_ids' => [1, 3]]);
        $this->assertSame([1, 3], $two->pluck('recipient_id')->sort()->values()->all());

        $this->assertSame('John Doe', $resolver->label('staff', ['staff_ids' => [1]]));
        $this->assertSame('2 selected staff', $resolver->label('staff', ['staff_ids' => [1, 2]]));
        $this->assertCount(0, $resolver->resolve('staff', ['staff_ids' => []]), 'nobody picked means nobody');
        $this->assertSame([1, 2], $resolver->resolve('staff_all')->pluck('recipient_id')->sort()->values()->all(), '"all staff" is still active staff only');
    }

    public function test_a_single_staff_member_can_be_emailed_and_logged(): void
    {
        Mail::fake();
        $this->seedStaff();

        [$batch, $error] = app(CommBatchService::class)->dispatch('email', 'staff', ['staff_ids' => [1]], 'Hello {first_name}', 'Dear {name}, welcome.', 7);

        $this->assertNull($error);
        $batch = CommBatch::find($batch->id);
        $this->assertSame('John Doe', $batch->audience_label);
        $this->assertSame(1, $batch->sent_count);
        Mail::assertSent(GenericCommMail::class, fn ($m) => $m->hasTo('john.doe@chabrin.example'));
        $this->assertDatabaseHas('cb_email_logs', ['batch_id' => $batch->id, 'recipient_type' => 'staff', 'recipient_id' => 1, 'subject' => 'Hello John', 'status' => 'sent']);
    }

    public function test_a_staff_member_without_an_email_is_refused_clearly(): void
    {
        $this->seedStaff();

        [$batch, $error] = app(CommBatchService::class)->dispatch('email', 'staff', ['staff_ids' => [2]], 'S', 'B', 7);

        $this->assertNull($batch);
        $this->assertStringContainsString('email address', $error);
    }

    public function test_staff_audience_needs_people_to_be_picked(): void
    {
        $controller = app(CommunicationsController::class);

        $this->expectException(ValidationException::class);
        $controller->estimate(Request::create('/x', 'POST', ['channel' => 'email', 'audience_type' => 'staff']));
    }

    public function test_estimate_counts_picked_staff(): void
    {
        $this->seedStaff();

        $data = app(CommunicationsController::class)->estimate(Request::create('/x', 'POST', ['channel' => 'sms', 'audience_type' => 'staff', 'staff_ids' => [1, 2, 3]]))->getData(true);

        $this->assertSame(3, $data['total']);
        $this->assertSame(2, $data['sendable'], 'Peter has no mobile');
        $this->assertSame(1, $data['skipped']);
        $this->assertSame('3 selected staff', $data['audience_label']);
    }

    public function test_staff_search_matches_name_number_or_email_and_hides_terminated_staff(): void
    {
        $this->seedStaff();
        $controller = app(CommunicationsController::class);
        $search = fn (string $q) => array_column($controller->staffSearch(Request::create('/x', 'GET', ['q' => $q]))->getData(true)['results'], 'id');

        $this->assertSame([1], $search('john'));
        $this->assertSame([2], $search('emp002'));
        $this->assertSame([3], $search('peter@chabrin'));
        $this->assertSame([], $search('gone'), 'terminated staff are not offered');
        $this->assertSame([1, 2, 3], array_map('intval', $search('')), 'active and on-leave staff, alphabetical by name');
    }

    public function test_staff_search_is_refused_without_the_compose_permission(): void
    {
        $this->seedStaff();
        DB::table('cb_role_user')->insert(['user_id' => 11, 'role_id' => 52]);
        DB::table('cb_sub_module_permissions')->insert(['role_id' => 52, 'sub_module_id' => 3]); // Logs only
        $this->actingAs(new \Illuminate\Auth\GenericUser(['id' => 11]));

        $this->assertSame(403, app(CommunicationsController::class)->staffSearch(Request::create('/x', 'GET', ['q' => 'john']))->getStatusCode());
    }

    public function test_first_name_skips_a_leading_title(): void
    {
        $this->assertSame('David', CommBatchService::firstName('Mr David Kiprono Bett'));
        $this->assertSame('Jane', CommBatchService::firstName('Dr. Jane Doe'));
        $this->assertSame('Grace', CommBatchService::firstName('MRS Grace Njeri'));
        $this->assertSame('Jane', CommBatchService::firstName('Jane Wanjiru'));
        $this->assertSame('Mr', CommBatchService::firstName('Mr'), 'a name that is only a title is kept whole');
        $this->assertSame('Madam', CommBatchService::firstName('Madam'));
        $this->assertSame('Dear David,', CommBatchService::substitute('Dear {first_name},', ['name' => 'Mr David Kiprono Bett']));
        $this->assertSame('Mr David Kiprono Bett', CommBatchService::substitute('{name}', ['name' => 'Mr David Kiprono Bett']), '{name} stays the full stored name');
    }

    public function test_a_picked_tenant_gets_the_property_and_unit_of_their_open_lease(): void
    {
        $this->seedTenants();
        $resolver = new AudienceResolver();

        $active = $resolver->resolve('single_client', ['client_id' => 101])->first();
        $this->assertSame('Alpha Court', $active['property']);
        $this->assertSame('A1', $active['unit']);

        $exited = $resolver->resolve('single_client', ['client_id' => 103])->first();
        $this->assertNull($exited['property'], 'a tenant whose lease ended has no current property');

        $landlord = $resolver->resolve('single_client', ['client_id' => 201])->first();
        $this->assertNull($landlord['property']);
        $this->assertSame('landlord', $landlord['recipient_type']);

        $many = $resolver->resolve('clients', ['client_ids' => [101, 102]]);
        $this->assertSame(['Alpha Court', 'Beta Villas'], $many->pluck('property', 'recipient_id')->sortKeys()->values()->all());
    }

    public function test_batch_message_returns_the_text_as_written(): void
    {
        $batch = CommBatch::create(['channel' => 'email', 'subject' => 'Notice', 'body' => "Dear {name},\nSee you.", 'audience_type' => 'tenants_all', 'audience_label' => 'All current tenants', 'attachment_name' => 'notice.pdf', 'recipient_count' => 5, 'sent_count' => 4, 'failed_count' => 1, 'skipped_count' => 0, 'status' => 'done', 'created_by' => 7]);

        $data = app(CommunicationsController::class)->batchMessage($batch->id)->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame("Dear {name},\nSee you.", $data['body'], 'the placeholders are left as written');
        $this->assertSame('All current tenants', $data['audience']);
        $this->assertSame('notice.pdf', $data['attachment']);
        $this->assertSame('Test Director', $data['sender']);
        $this->assertSame(1, $data['failed']);
    }

    public function test_log_message_turns_a_stored_html_body_into_safe_plain_text(): void
    {
        $id = DB::table('cb_email_logs')->insertGetId(['batch_id' => null, 'type' => 'general', 'recipient_name' => 'Jane', 'recipient_email' => 'jane@example.com', 'subject' => 'Hi', 'body' => 'Dear Jane,<br />Pay &amp; save<br><br><br>&lt;script&gt;alert(1)&lt;/script&gt;<p>Bye</p>', 'status' => 'sent', 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $data = app(CommunicationsController::class)->logMessage('email', $id)->getData(true);

        $this->assertSame("Dear Jane,\nPay & save\n\n<script>alert(1)</script>\nBye", $data['body'], 'tags are dropped and entities decoded, so the popup can only ever show text');
        $this->assertSame('jane@example.com', $data['contact']);
        $this->assertSame('sent', $data['status']);
    }

    public function test_log_message_for_sms_and_an_unknown_channel(): void
    {
        $id = DB::table('cb_sms_logs')->insertGetId(['batch_id' => null, 'type' => 'test', 'recipient_name' => 'Jane', 'recipient_phone' => '0722000000', 'message' => 'Hello Jane', 'units' => 1, 'status' => 'failed', 'error' => 'No gateway', 'message_id' => 'ATX1', 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $controller = app(CommunicationsController::class);

        $sms = $controller->logMessage('sms', $id)->getData(true);
        $this->assertSame('Hello Jane', $sms['body']);
        $this->assertSame('0722000000', $sms['contact']);
        $this->assertSame('ATX1', $sms['message_id']);
        $this->assertNull($sms['subject']);

        $this->assertSame(404, $controller->logMessage('fax', $id)->getStatusCode());
    }

    public function test_who_may_read_which_message(): void
    {
        $batch = CommBatch::create(['channel' => 'email', 'body' => 'x', 'audience_type' => 'tenants_all', 'recipient_count' => 1, 'status' => 'done', 'created_by' => 7]);
        $row = fn ($batchId) => ['batch_id' => $batchId, 'type' => 'general', 'recipient_name' => 'Jane', 'recipient_email' => 'jane@example.com', 'subject' => 'S', 'body' => 'secret payslip', 'status' => 'sent', 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        $batchLog = DB::table('cb_email_logs')->insertGetId($row($batch->id));
        $systemLog = DB::table('cb_email_logs')->insertGetId($row(null));

        // role 60 holds only the Sent page; role 61 only Logs; role 62 neither
        foreach ([[12, 60, 1], [13, 61, 3]] as [$user, $role, $sub]) {
            DB::table('cb_role_user')->insert(['user_id' => $user, 'role_id' => $role]);
            DB::table('cb_sub_module_permissions')->insert(['role_id' => $role, 'sub_module_id' => $sub]);
        }
        DB::table('cb_role_user')->insert(['user_id' => 14, 'role_id' => 62]);
        $controller = app(CommunicationsController::class);
        $as = function (int $user) { $this->actingAs(new \Illuminate\Auth\GenericUser(['id' => $user])); };

        $as(12); // Sent page only
        $this->assertSame(200, $controller->logMessage('email', $batchLog)->getStatusCode(), 'a recipient of a batch can be read from the Sent page');
        $this->assertSame(403, $controller->logMessage('email', $systemLog)->getStatusCode(), 'a system email such as a payslip is for the Logs page only');
        $this->assertSame(200, $controller->batchMessage($batch->id)->getStatusCode());

        $as(13); // Logs page only
        $this->assertSame(200, $controller->logMessage('email', $systemLog)->getStatusCode());
        $this->assertSame(200, $controller->logMessage('email', $batchLog)->getStatusCode());
        $this->assertSame(403, $controller->batchMessage($batch->id)->getStatusCode(), 'the batch page needs the Sent permission');

        $as(14); // neither
        $this->assertSame(403, $controller->logMessage('email', $batchLog)->getStatusCode());
        $this->assertSame(403, $controller->logMessage('email', $systemLog)->getStatusCode());
        $this->assertSame(403, $controller->batchMessage($batch->id)->getStatusCode());
    }

    private function seedEmailLogs(int $count, ?int $batchId = null, string $status = 'sent', string $sentAt = '2026-11-01 10:00:00'): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['batch_id' => $batchId, 'type' => 'general', 'recipient_type' => 'tenant', 'recipient_id' => $i, 'recipient_name' => 'Person ' . $i, 'recipient_email' => "p{$i}@example.com", 'subject' => 'S', 'body' => 'B', 'status' => $status, 'sent_at' => $sentAt, 'created_at' => $sentAt, 'updated_at' => $sentAt];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('cb_email_logs')->insert($chunk);
        }
    }

    private function logsPage(array $params): array
    {
        return app(CommunicationsController::class)->logsData(Request::create('/x', 'GET', $params + ['channel' => 'email']))->getData(true);
    }

    public function test_logs_come_one_page_at_a_time_newest_first(): void
    {
        $this->seedEmailLogs(120);

        $first = $this->logsPage(['draw' => 3, 'start' => 0, 'length' => 50]);
        $this->assertSame(3, $first['draw'], 'DataTables expects its draw number echoed back');
        $this->assertSame(120, $first['recordsTotal']);
        $this->assertSame(120, $first['recordsFiltered']);
        $this->assertFalse($first['capped']);
        $this->assertCount(50, $first['data']);
        $this->assertSame(120, $first['data'][0]['id'], 'newest first');
        $this->assertSame(71, $first['data'][49]['id']);

        $last = $this->logsPage(['start' => 100, 'length' => 50]);
        $this->assertCount(20, $last['data'], 'the last page holds what is left');
        $this->assertSame(20, $last['data'][0]['id']);

        $oldest = $this->logsPage(['start' => 0, 'length' => 10, 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $this->assertSame(1, $oldest['data'][0]['id'], 'the date column can be flipped to oldest first');

        $this->assertCount(100, $this->logsPage(['start' => 0, 'length' => 5000])['data'], 'a page is never more than 100 rows, whatever is asked');
        $this->assertCount(10, $this->logsPage(['start' => 0, 'length' => 1])['data'], 'and never fewer than 10');
        $this->assertCount(50, $this->logsPage([])['data'], 'with no paging parameters the page is 50');
    }

    public function test_date_filter_includes_both_end_days_and_is_index_friendly(): void
    {
        foreach (['2026-10-09 23:59:59', '2026-10-10 00:00:00', '2026-11-10 23:59:59', '2026-11-11 00:00:00'] as $i => $when) {
            $this->seedEmailLogs(1, null, 'sent', $when);
            DB::table('cb_email_logs')->where('id', $i + 1)->update(['recipient_name' => $when]);
        }

        $queries = [];
        DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });
        $page = $this->logsPage(['date_from' => '2026-10-10', 'date_to' => '2026-11-10', 'start' => 0, 'length' => 50]);

        $this->assertSame(['2026-11-10 23:59:59', '2026-10-10 00:00:00'], array_column($page['data'], 'recipient'), 'the first and last day are both included, nothing outside');
        $this->assertSame(2, $page['recordsTotal']);

        $logSql = implode(' ', array_filter($queries, fn ($sql) => str_contains($sql, 'cb_email_logs')));
        $this->assertStringNotContainsString('strftime', $logSql, 'the filter must compare the raw column, not wrap it in DATE(), or Postgres cannot use the index');
        $this->assertStringNotContainsString('::date', $logSql);
    }

    public function test_bad_dates_are_ignored_not_fatal(): void
    {
        $this->seedEmailLogs(3);

        $page = $this->logsPage(['date_from' => 'yesterday', 'date_to' => '2026-13-45']);

        $this->assertSame(3, $page['recordsTotal']);
    }

    public function test_logs_recipient_search_and_status_filter_narrow_the_total(): void
    {
        $this->seedEmailLogs(30);
        DB::table('cb_email_logs')->where('id', '<=', 4)->update(['status' => 'failed']);

        $this->assertSame(1, $this->logsPage(['q' => 'person 7'])['recordsTotal'], 'matches the name');
        $this->assertSame(11, $this->logsPage(['q' => 'P2'])['recordsTotal'], 'matches the email too, case-insensitively: p2, p20..p29');
        $this->assertSame(4, $this->logsPage(['status' => 'failed'])['recordsTotal']);
        $this->assertSame(1, $this->logsPage(['status' => 'failed', 'q' => 'Person 3'])['recordsTotal']);
    }

    public function test_a_batchs_recipients_page_through_with_status_and_search(): void
    {
        $batch = CommBatch::create(['channel' => 'email', 'body' => 'x', 'audience_type' => 'tenants_all', 'recipient_count' => 140, 'status' => 'done', 'created_by' => 7]);
        $this->seedEmailLogs(120, $batch->id, 'sent');
        $this->seedEmailLogs(20, $batch->id, 'failed');
        $this->seedEmailLogs(500, null, 'sent'); // other messages must not leak in
        $controller = app(CommunicationsController::class);
        $page = fn (array $p) => $controller->batchLogsData(Request::create('/x', 'GET', $p), $batch->id)->getData(true);

        $one = $page(['draw' => 9, 'start' => 0, 'length' => 50]);
        $this->assertSame(9, $one['draw']);
        $this->assertSame(140, $one['recordsTotal'], 'only this batch\'s rows');
        $this->assertCount(50, $one['data']);

        $this->assertCount(40, $page(['start' => 100, 'length' => 50])['data']);
        $this->assertSame(20, $page(['status' => 'failed'])['recordsTotal']);
        $this->assertSame(12, $page(['search' => ['value' => 'person 7']])['recordsTotal'], 'the box matches names: Person 7 and 70-79 among the 120 delivered, Person 7 among the 20 failed');
        $this->assertSame(2, $page(['search' => ['value' => 'p5@']])['recordsTotal'], 'and emails');

        $this->assertSame(['Person 1', 'Person 2'], array_slice(array_column($page(['start' => 0, 'length' => 10])['data'], 'recipient'), 0, 2), 'a batch reads in the order it was processed');
        $this->assertSame('Person 120', $page(['status' => 'sent', 'start' => 119, 'length' => 10])['data'][0]['recipient'], 'every one of the 120 delivered rows is reachable by paging');
        $this->assertSame('Person 20', $page(['start' => 139, 'length' => 10])['data'][0]['recipient'], 'and the very last row of the batch');
    }

    public function test_the_count_stops_at_the_cap_and_says_so(): void
    {
        $this->seedEmailLogs(5);
        $controller = app(CommunicationsController::class);
        $count = new \ReflectionMethod($controller, 'countCapped');
        $count->setAccessible(true);

        $this->assertSame([3, true], $count->invoke($controller, \App\Models\EmailLog::query(), 3), '5 rows with a cap of 3: counted up to the cap, flagged as more');
        $this->assertSame([5, false], $count->invoke($controller, \App\Models\EmailLog::query(), 5), 'exactly the cap is not "more"');
        $this->assertSame([5, false], $count->invoke($controller, \App\Models\EmailLog::query(), 100));
        $this->assertSame([2, false], $count->invoke($controller, \App\Models\EmailLog::query()->where('id', '<=', 2), 100));
    }

    public function test_sent_batches_date_range_is_inclusive_and_index_friendly(): void
    {
        foreach (['2026-10-09 23:59:59', '2026-10-10 00:00:00', '2026-11-10 23:59:59', '2026-11-11 00:00:00'] as $when) {
            CommBatch::create(['channel' => 'email', 'body' => 'x', 'audience_type' => 'tenants_all', 'status' => 'done', 'created_by' => 7, 'created_at' => $when, 'updated_at' => $when]);
        }

        $queries = [];
        DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });
        $data = app(CommunicationsController::class)->batchesData(Request::create('/x', 'GET', ['date_from' => '2026-10-10', 'date_to' => '2026-11-10']))->getData(true);

        $this->assertSame(2, $data['count']);
        $this->assertStringNotContainsString('strftime', implode(' ', array_filter($queries, fn ($sql) => str_contains($sql, 'cb_comm_batches'))));
    }

    public function test_current_tenants_exclude_the_stale_leases_of_the_migrated_data(): void
    {
        $this->seedTenants();
        DB::table('sys_pm_clients')->insert([
            ['id' => 104, 'names' => 'Terminated But Open', 'email_address' => 't@example.com', 'mobile_number' => '0700000004', 'reference_number' => 'TEN-104', 'type_id' => '2'],
            ['id' => 105, 'names' => 'Active On Vacant Unit', 'email_address' => 'v@example.com', 'mobile_number' => '0700000005', 'reference_number' => 'TEN-105', 'type_id' => '2'],
            ['id' => 106, 'names' => 'Old Tenant Same Unit', 'email_address' => 'o@example.com', 'mobile_number' => '0700000006', 'reference_number' => 'TEN-106', 'type_id' => '2'],
        ]);
        DB::table('sys_pm_units')->insert([
            ['id' => 13, 'property_id' => 1, 'unit_name' => 'A3', 'occupancy_status_id' => 1],
            ['id' => 14, 'property_id' => 1, 'unit_name' => 'A4', 'occupancy_status_id' => 3],
            ['id' => 15, 'property_id' => 1, 'unit_name' => 'A5', 'occupancy_status_id' => 1],
        ]);
        DB::table('sys_pm_lease_agreements')->insert([
            // marked Terminated but never given an end date: 15,000 of these in the real data
            ['id' => 4, 'tenant_id' => 104, 'client_id' => 201, 'unit_id' => 13, 'property_id' => 1, 'date_start' => '2025-03-01', 'date_end' => null, 'lease_status' => 'Terminated'],
            // Active, but the unit has since been marked vacant: the tenant has left
            ['id' => 5, 'tenant_id' => 105, 'client_id' => 201, 'unit_id' => 14, 'property_id' => 1, 'date_start' => '2025-03-01', 'date_end' => null, 'lease_status' => 'Active'],
            // two Active leases on one unit: only the newest start date is the person living there
            ['id' => 6, 'tenant_id' => 106, 'client_id' => 201, 'unit_id' => 15, 'property_id' => 1, 'date_start' => '2023-01-01', 'date_end' => null, 'lease_status' => 'Active'],
            ['id' => 7, 'tenant_id' => 101, 'client_id' => 201, 'unit_id' => 15, 'property_id' => 1, 'date_start' => '2025-06-01', 'date_end' => null, 'lease_status' => 'Active'],
        ]);
        $resolver = new AudienceResolver();

        $all = $resolver->resolve('tenants_all')->pluck('recipient_id')->sort()->values()->all();
        $this->assertSame([101, 102], $all, 'not 103 (ended), 104 (Terminated), 105 (vacant unit) or 106 (an older lease on a unit someone else now holds)');

        $property = $resolver->resolve('property', ['property_id' => 1])->pluck('recipient_id')->all();
        $this->assertSame([101], $property, 'a property audience uses the same rule');

        $this->assertSame([102], $resolver->resolve('zone', ['zone_id' => 6])->pluck('recipient_id')->all());
    }

    public function test_a_picked_tenant_gets_the_property_of_their_active_lease_not_a_terminated_one(): void
    {
        $this->seedTenants();
        DB::table('sys_pm_units')->insert(['id' => 16, 'property_id' => 2, 'unit_name' => 'B9', 'occupancy_status_id' => 1]);
        // newer lease at Beta Villas, but Terminated: the Active one at Alpha Court is the real home
        DB::table('sys_pm_lease_agreements')->insert(['id' => 8, 'tenant_id' => 101, 'client_id' => 201, 'unit_id' => 16, 'property_id' => 2, 'date_start' => '2026-09-01', 'date_end' => null, 'lease_status' => 'Terminated']);

        $tenant = (new AudienceResolver())->resolve('single_client', ['client_id' => 101])->first();

        $this->assertSame('Alpha Court', $tenant['property']);
        $this->assertSame('A1', $tenant['unit']);
    }

    public function test_the_tenant_record_wins_over_the_old_shared_row(): void
    {
        $this->seedTenants();
        // Jane's email was corrected on the Tenants screen (tenant record); the old row still has the stale one
        DB::table('cb_tenant_records')->insert(['id' => 101, 'names' => 'Jane Wanjiru Kamau', 'phone' => '0799111222', 'email' => 'jane.new@example.com']);
        $resolver = new AudienceResolver();

        $all = $resolver->resolve('tenants_all')->keyBy('recipient_id');
        $this->assertSame('jane.new@example.com', $all[101]['email']);
        $this->assertSame('0799111222', $all[101]['mobile']);
        $this->assertSame('Jane Wanjiru Kamau', $all[101]['name']);

        $picked = $resolver->resolve('single_client', ['client_id' => 101])->first();
        $this->assertSame('jane.new@example.com', $picked['email'], 'picking the tenant by name uses the same value');
        $this->assertSame('Jane Wanjiru Kamau', $resolver->label('single_client', ['client_id' => 101]));
    }

    public function test_a_contact_held_only_in_the_tenant_record_is_reachable_and_a_blank_one_falls_back(): void
    {
        $this->seedTenants();
        // John (102) has no email on the old row; his record has one. Jane (101) has a record with a blank email and phone.
        DB::table('cb_tenant_records')->insert([
            ['id' => 102, 'names' => 'John Otieno', 'phone' => null, 'email' => 'john@new.example'],
            ['id' => 101, 'names' => '  ', 'phone' => '', 'email' => ' '],
        ]);

        $all = (new AudienceResolver())->resolve('tenants_all')->keyBy('recipient_id');

        $this->assertSame('john@new.example', $all[102]['email'], 'an email that exists only in the tenant record is used');
        $this->assertSame('0700000002', $all[102]['mobile'], 'a missing phone in the record falls back to the old row');
        $this->assertSame('jane@example.com', $all[101]['email'], 'blank record values never hide the old row\'s contact');
        $this->assertSame('Jane Wanjiru', $all[101]['name']);

        Mail::fake();
        [$batch, $error] = app(CommBatchService::class)->dispatch('email', 'single_client', ['client_id' => 102], 'S', 'B', 7);
        $this->assertNull($error, 'John could not be emailed before, now he can');
    }

    public function test_the_tenant_record_never_overrides_a_landlord(): void
    {
        $this->seedTenants();
        DB::table('cb_tenant_records')->insert(['id' => 201, 'names' => 'Not The Landlord', 'phone' => '0000', 'email' => 'bogus@example.com']);

        $landlord = (new AudienceResolver())->resolve('single_client', ['client_id' => 201])->first();

        $this->assertSame('landlord@example.com', $landlord['email']);
        $this->assertSame('Landlord One', $landlord['name']);
    }

    public function test_client_search_finds_and_shows_the_tenant_records_name(): void
    {
        $this->seedTenants();
        DB::table('cb_tenant_records')->insert(['id' => 101, 'names' => 'Jane Renamed', 'phone' => null, 'email' => null]);
        $controller = app(CommunicationsController::class);
        $search = fn (string $q) => $controller->clientSearch(Request::create('/x', 'GET', ['q' => $q, 'kind' => 'tenant']))->getData(true)['results'];

        $byNew = $search('renamed');
        $this->assertSame([101], array_column($byNew, 'id'));
        $this->assertSame('Jane Renamed (TEN-101)', $byNew[0]['text']);
        $this->assertSame([101], array_column($search('wanjiru'), 'id'), 'the old name still finds her');
        $this->assertSame('jane@example.com', $byNew[0]['email'], 'blank record email falls back');
    }
}
