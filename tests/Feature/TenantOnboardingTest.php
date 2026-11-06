<?php

namespace Tests\Feature;

use App\Http\Controllers\TenantsController;
use App\Models\Client;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Services\TenantOnboardingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TenantOnboardingTest extends TestCase
{
    private $dispatcher;
    private $sqliteResolver;
    private TenantOnboardingService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '', 'tenant_onboarding.enabled' => true,
            'accounting.post_invoice_journals' => false]);
        $this->sqliteResolver = \Illuminate\Database\Connection::getResolver('sqlite');
        // Production's legacy invoice lookup uses PostgreSQL ILIKE. SQLite's LIKE
        // is case-insensitive for these ASCII billing names in the isolated test DB.
        \Illuminate\Database\Connection::resolverFor('sqlite', function ($pdo, $database, $prefix, $config) {
            return new class($pdo, $database, $prefix, $config) extends \Illuminate\Database\SQLiteConnection {
                public function select($query, $bindings = [], $useReadPdo = true)
                {
                    return parent::select(str_replace(' ILIKE ', ' LIKE ', $query), $bindings, $useReadPdo);
                }
            };
        });
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Storage::fake('local');
        Storage::fake('public');
        $this->dispatcher = Model::getEventDispatcher();
        Model::unsetEventDispatcher();
        $this->withoutMiddleware([
            \App\Http\Middleware\AccessChecker::class,
            \App\Http\Middleware\OneLogin::class,
            \App\Http\Middleware\EnsureStaffPasswordIsCurrent::class,
        ]);
        foreach ([Client::class, \App\Models\TenantRecord::class, \App\Models\Property::class,
            \App\Models\Unit::class, \App\Models\ServiceAgreement::class, \App\Models\LeaseAgreement::class,
            \App\Models\InvoiceableItem::class, \App\Models\DebtorInvoice::class, \App\Models\TenantAccountTransaction::class,
            \App\Models\TenantBankDetail::class, \App\Models\TenantOtherContact::class, \App\Models\TenantNextOfKin::class,
            \App\Models\ClientDocument::class, \App\Models\MoveInInspection::class, \App\Models\Gender::class,
            \App\Models\Salutation::class, \App\Models\Deposit::class, \App\Models\TempBatchUnit::class,
            \App\Models\TempBatchTenant::class, \App\Models\TempUploadBatch::class] as $class) {
            $model = new $class();
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->increments('id');
                $columns = $model->getFillable();
                if ($model instanceof \App\Models\Property) $columns[] = 'active';
                if ($model instanceof \App\Models\ServiceAgreement) $columns[] = 'move_in_inspection_required';
                foreach (array_unique($columns) as $column) {
                    if ($column !== 'id') $table->text($column)->nullable();
                }
                if ($model->usesTimestamps()) $table->timestamps();
                if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)) $table->softDeletes();
            });
        }
        Schema::create('josya_users', function (Blueprint $table) { $table->id(); $table->string('name'); $table->integer('block')->default(0); });
        Schema::create('cb_roles', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('display_name'); $table->boolean('is_global')->default(false); });
        Schema::create('cb_role_user', function (Blueprint $table) { $table->integer('role_id'); $table->integer('user_id'); });
        Schema::create('cb_sub_modules', function (Blueprint $table) { $table->id(); $table->integer('order'); $table->integer('module_id'); });
        Schema::create('cb_sub_module_permissions', function (Blueprint $table) { $table->integer('role_id'); $table->integer('sub_module_id'); });
        Schema::create('cb_modules', function (Blueprint $table) { $table->id(); $table->integer('module_order'); });
        Schema::create('cb_super_modules', function (Blueprint $table) { $table->id(); $table->integer('order'); });
        Schema::create('sys_user_staff_links', function (Blueprint $table) { $table->integer('user_id'); $table->integer('staff_id'); });
        Schema::create('sys_set_zones', function (Blueprint $table) { $table->id(); $table->integer('zone_manager_id'); $table->integer('zone_supervisor_id')->nullable(); $table->integer('zone_auditor_id')->nullable(); });
        (require database_path('migrations/2026_11_05_120000_create_tenant_onboarding_submissions.php'))->up();
        foreach (array_values(TenantOnboarding::STAGES) as $index => $name) {
            $id = $index + 1;
            DB::table('josya_users')->insert(['id' => $id, 'name' => $name]);
            DB::table('cb_roles')->insert(['id' => $id, 'name' => $name, 'display_name' => $name]);
            DB::table('cb_role_user')->insert(['role_id' => $id, 'user_id' => $id]);
            DB::table('sys_user_staff_links')->insert(['user_id' => $id, 'staff_id' => $id + 100]);
        }
        DB::table('sys_set_zones')->insert(['id' => 1, 'zone_manager_id' => 102]);
        DB::table('sys_pm_properties')->insert(['id' => 1, 'property_name' => 'Test House', 'active' => 1, 'client_id' => 900, 'zone_id' => 1, 'field_officer_id' => 101]);
        DB::table('sys_pm_units')->insert(['id' => 1, 'property_id' => 1, 'unit_code' => 'TEST-B1', 'rent_amount' => 10000, 'occupancy_status_id' => 31, 'vat_able' => 'No']);
        DB::table('sys_pm_service_agreements')->insert(['id' => 1, 'property_id' => 1, 'current_status_id' => 5, 'date_start' => '2026-01-01', 'commission_percentage' => 10]);
        DB::table((new \App\Models\InvoiceableItem())->getTable())->insert(['id' => 1, 'item_name' => 'RENT', 'billing_code' => '0024']);
        $this->workflow = app(TenantOnboardingService::class);
    }

    protected function tearDown(): void
    {
        Model::setEventDispatcher($this->dispatcher);
        \Illuminate\Database\Connection::resolverFor('sqlite', $this->sqliteResolver ?? fn ($pdo, $database, $prefix, $config) => new \Illuminate\Database\SQLiteConnection($pdo, $database, $prefix, $config));
        parent::tearDown();
    }

    private function actor(int $id): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => $id, 'name' => 'Reviewer '.$id]);
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($name) => $name === 'create_tenants');
        $user->shouldReceive('linkedStaff')->andReturn(null);
        $user->setRelation('roles', collect());
        Auth::setUser($user);
        return $user;
    }

    private function form(array $override = []): array
    {
        return array_replace([
            'tenant_type' => 3, 'company_name' => 'Test Tenant', 'mobile_number' => '254700000000',
            'property_id' => 1, 'unit_id' => 1, 'registered_date' => '2026-11-01',
            'lease_start_date' => '2026-12-01', 'lease_years' => 1, 'frequency' => 'Annually',
            'deposit_months' => 2, 'deposit_held_by' => 'Company',
        ], $override);
    }

    private function submit(array $override = []): TenantOnboarding
    {
        $this->actingAs($this->actor(1));
        $response = $this->post(route('units.tenants.store'), $this->form($override));
        $response->assertSessionHasNoErrors()->assertRedirect();
        return TenantOnboarding::latest('id')->firstOrFail();
    }

    private function sign(TenantOnboarding $submission, int $actor): TenantOnboarding
    {
        $submission->refresh();
        return $this->workflow->decide($submission, $this->actor($actor), $submission->revision, $submission->stage, 'sign', null);
    }

    public function test_effecting_creates_the_tenant_lease_and_exactly_one_rent_bill_on_that_day(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-05 09:00:00'));
        $submission = $this->submit();
        $this->assertSame('zone_manager', $submission->stage);
        $this->assertDatabaseCount('sys_pm_clients', 0);
        $this->assertDatabaseCount('sys_pm_lease_agreements', 0);
        $this->assertDatabaseCount('sys_pm_debtors_invoices', 0);
        $this->assertSame('31', (string) DB::table('sys_pm_units')->value('occupancy_status_id'));
        $submission = $this->sign($submission, 2);
        $submission = $this->sign($submission, 3);
        $this->assertDatabaseCount('sys_pm_clients', 0);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-08 10:30:00'));
        \Illuminate\Support\Facades\Exceptions::fake();
        \Illuminate\Support\Facades\Exceptions::stopIgnoring(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->actingAs($this->actor(4));
        $response = $this->post(route('tenant-onboarding.effect', $submission), ['revision' => 1]);
        \Illuminate\Support\Facades\Exceptions::throwFirstReported();
        $this->assertFalse(session()->has('error'), (string) session('error'));
        $response->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error')->assertSessionHas('success');
        $submission->refresh();
        $this->assertSame('effected', $submission->state);
        $this->assertDatabaseCount('sys_pm_clients', 1);
        $this->assertDatabaseHas('sys_pm_lease_agreements', ['lease_status' => 'Active', 'current_status_id' => 5]);
        $this->assertSame('2026-11-08', \App\Models\LeaseAgreement::first()->date_start->toDateString());
        $this->assertDatabaseHas('sys_pm_debtors_invoices', ['total_amount' => 10000]);
        $invoice = \App\Models\DebtorInvoice::first();
        $this->assertSame('2026-11-08', \Carbon\Carbon::parse($invoice->date_due)->toDateString());
        $this->assertSame('2026-11-01', \Carbon\Carbon::parse($invoice->returns_period)->toDateString());
        $this->assertDatabaseCount('sys_pm_tenants_accounts_trans', 1);
        $this->assertNull($submission->reserved_unit_id);
        $this->post(route('tenant-onboarding.effect', $submission), ['revision' => 1])->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('sys_pm_debtors_invoices', 1);
    }

    public function test_later_reviewers_cannot_skip_a_stage(): void
    {
        $submission = $this->submit();
        $this->actingAs($this->actor(4));
        $this->post(route('tenant-onboarding.effect', $submission), ['revision' => 1])->assertSessionHasErrors('revision');
        $this->postJson(route('tenant-onboarding.decide', $submission), ['revision' => 1, 'stage' => 'zone_manager', 'action' => 'sign'])->assertForbidden();
        $this->assertDatabaseCount('sys_pm_clients', 0);
    }

    public function test_invoice_failure_rolls_back_activation_and_allows_a_retry(): void
    {
        $submission = $this->sign($this->sign($this->submit(), 2), 3);
        DB::table((new \App\Models\InvoiceableItem())->getTable())->delete();
        $this->actingAs($this->actor(4));
        $this->post(route('tenant-onboarding.effect', $submission), ['revision' => 1])->assertSessionHas('error');
        $this->assertSame('pending', $submission->fresh()->state);
        $this->assertDatabaseCount('sys_pm_clients', 0);
        $this->assertDatabaseCount('sys_pm_lease_agreements', 0);
        $this->assertSame('31', (string) DB::table('sys_pm_units')->value('occupancy_status_id'));
        DB::table((new \App\Models\InvoiceableItem())->getTable())->insert(['id' => 1, 'item_name' => 'RENT', 'billing_code' => '0024']);
        $this->post(route('tenant-onboarding.effect', $submission), ['revision' => 1])->assertSessionHas('success');
        $this->assertSame('effected', $submission->fresh()->state);
    }

    public function test_corrections_create_a_new_version_and_require_fresh_signatures(): void
    {
        $submission = $this->submit();
        $this->workflow->decide($submission, $this->actor(2), 1, 'zone_manager', 'return', 'Correct phone');
        $this->actingAs($this->actor(1));
        $this->post(route('units.tenants.store'), $this->form(['mobile_number' => '254711111111', 'onboarding_id' => $submission->id, 'onboarding_revision' => 1]))->assertSessionHasNoErrors();
        $submission->refresh();
        $this->assertSame(2, $submission->revision);
        $this->assertSame('zone_manager', $submission->stage);
        $this->assertSame(2, DB::table('tenant_onboarding_events')->where('action', 'submitted')->count());
        $this->assertStringNotContainsString('254711111111', DB::table('tenant_onboarding_submissions')->value('payload'));
    }

    public function test_reservations_prevent_duplicate_submissions_and_rejection_releases_unit(): void
    {
        $submission = $this->submit();
        $this->post(route('units.tenants.store'), $this->form())->assertSessionHasErrors('unit_id');
        $this->workflow->decide($submission, $this->actor(2), 1, 'zone_manager', 'reject', 'Withdrawn');
        $this->assertNull($submission->fresh()->reserved_unit_id);
        $this->submit(['company_name' => 'Next Tenant']);
        $this->assertDatabaseCount('tenant_onboarding_submissions', 2);
    }

    public function test_changed_rent_blocks_effecting_until_reviewed_again(): void
    {
        $submission = $this->sign($this->sign($this->submit(), 2), 3);
        DB::table('sys_pm_units')->update(['rent_amount' => 12000]);
        $this->actingAs($this->actor(4));
        $this->post(route('tenant-onboarding.effect', $submission), ['revision' => 1])->assertSessionHasErrors('unit_id');
        $this->assertDatabaseCount('sys_pm_clients', 0);
    }

    public function test_disabled_workflow_hides_the_review_screen(): void
    {
        config(['tenant_onboarding.enabled' => false]);
        $this->actingAs($this->actor(1));
        $this->getJson(route('tenant-onboarding.index'))->assertNotFound();
    }

    public function test_review_screens_render_and_unauthorised_users_cannot_read_documents(): void
    {
        $submission = $this->submit(['tenant_photo' => \Illuminate\Http\UploadedFile::fake()->image('portrait.jpg')]);
        $this->get(route('tenant-onboarding.index'))->assertOk()->assertSee('Test Tenant');
        $this->get(route('tenant-onboarding.show', $submission))->assertOk()->assertSee('Awaiting Zone Manager');
        $this->get(route('tenant-onboarding.document', [$submission, 0]))->assertOk();
        $this->actingAs($this->actor(4));
        $this->getJson(route('tenant-onboarding.document', [$submission, 0]))->assertForbidden();
        $this->getJson(route('tenant-onboarding.show', $submission))->assertForbidden();
        $this->workflow->decide($submission, $this->actor(2), 1, 'zone_manager', 'return', 'Update details');
        $this->actingAs($this->actor(1));
        $this->get(route('tenant-onboarding.correct', $submission))->assertOk()->assertSee('Resubmit for Review');
    }

    public function test_bulk_tenants_enter_the_same_workflow_and_are_created_only_when_effected(): void
    {
        $this->actor(1);
        $batch = \App\Models\TempUploadBatch::create(['property_id' => 1, 'client_id' => 900, 'status' => 'staged']);
        \App\Models\TempBatchUnit::create(['batch_id' => $batch->id, 'row_no' => 1, 'unit_number' => 'B2', 'rent_amount' => '8000']);
        $row = \App\Models\TempBatchTenant::create(['batch_id' => $batch->id, 'row_no' => 1, 'unit_number' => 'B2',
            'sheet' => 'corporate', 'company_name' => 'Imported Tenant', 'mobile_no' => '254700000001', 'lease_start_date' => '2026-10-01']);
        $batches = Mockery::mock(\App\Services\UnitTenantUpload\UnitTenantUploadBatchService::class);
        $batches->shouldReceive('revalidate')->once();
        $lookups = Mockery::mock(\App\Services\UnitTenantUpload\UnitTenantUploadLookups::class);
        $lookups->shouldReceive('id')->andReturn(1);
        $slaApprovals = Mockery::mock(\App\Services\ServiceAgreementApprovalService::class);
        $slaApprovals->shouldReceive('approveNewUnits')->once()->andReturn([]);
        $submitter = new \App\Services\UnitTenantUpload\UnitTenantSubmitService($batches, $lookups, $slaApprovals);
        $result = $submitter->submit($batch, 1);
        $this->assertSame(1, $result['submitted'], (string) $row->fresh()->error);
        $this->assertDatabaseCount('sys_pm_clients', 0);
        $this->assertDatabaseCount('sys_pm_lease_agreements', 0);
        $submission = TenantOnboarding::firstOrFail();
        $submission = $this->sign($this->sign($submission, 2), 3);
        $this->actingAs($this->actor(4));
        $this->post(route('tenant-onboarding.effect', $submission), ['revision' => 1])->assertSessionMissing('error')->assertSessionHas('success');
        $this->assertNotNull($row->fresh()->tenant_id);
        $this->assertDatabaseHas('sys_pm_lease_agreements', ['open_ended' => 1, 'date_end' => null]);
        $this->assertDatabaseHas('sys_pm_debtors_invoices', ['total_amount' => 8000]);
    }
}
