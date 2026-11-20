<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DebtorInvoice;
use App\Models\Deposit;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\ServiceAgreement;
use App\Models\TenantAccountTransaction;
use App\Models\TenantReview;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantReviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TenantReviewTest extends TestCase
{
    private TenantReviewService $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'tenant_review_test', 'database.connections.tenant_review_test' =>
            ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('tenant_review_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::connection()->setQueryGrammar(new class(DB::connection()) extends \Illuminate\Database\Query\Grammars\SQLiteGrammar {
            protected function whereRaw(\Illuminate\Database\Query\Builder $query, $where)
            {
                return str_replace(' ILIKE ', ' LIKE ', parent::whereRaw($query, $where));
            }
        });
        Event::fake();
        foreach ([new Client, new LeaseAgreement, new Property, new Unit, new ServiceAgreement,
            new DebtorInvoice, new TenantAccountTransaction, new Deposit, new \App\Models\InvoiceableItem,
            new \App\Models\TenantRecord, new \App\Models\Notification] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                if ($model instanceof Property) $table->boolean('active')->default(true);
                foreach (array_diff($model->getFillable(), ['id', 'created_at', 'updated_at']) as $field) $table->text($field)->nullable();
                if ($model->usesTimestamps()) $table->timestamps();
            });
        }
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('display_name'); $t->boolean('is_global')->default(true); });
        Schema::create('cb_role_user', function (Blueprint $t) { $t->integer('role_id'); $t->integer('user_id'); $t->string('user_type')->nullable(); });
        Schema::create('cb_permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('display_name')->nullable(); $t->text('description')->nullable(); $t->integer('module_id')->nullable(); $t->timestamps(); });
        Schema::create('cb_modules', function (Blueprint $t) { $t->id(); $t->string('module_name'); });
        Schema::create('cb_sub_modules', function (Blueprint $t) { $t->id(); $t->string('sub_module_name'); $t->string('sub_module_url'); $t->integer('module_id'); $t->string('icon')->nullable(); $t->integer('order'); $t->string('added_by')->nullable(); $t->timestamp('added_on')->nullable(); $t->timestamps(); });
        Schema::create('cb_sub_module_permissions', function (Blueprint $t) { $t->id(); $t->integer('role_id'); $t->integer('sub_module_id'); $t->string('added_by')->nullable(); $t->timestamp('added_on')->nullable(); });
        DB::table('cb_modules')->insert(['id' => 10, 'module_name' => 'Approval Control']);
        Schema::create('cb_permission_role', function (Blueprint $t) { $t->integer('permission_id'); $t->integer('role_id'); });
        Schema::create('cb_permission_user', function (Blueprint $t) { $t->integer('permission_id'); $t->integer('user_id'); $t->string('user_type'); });
        Schema::create('sys_user_staff_links', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('staff_id'); });
        Schema::create('sys_set_zones', function (Blueprint $t) { $t->id(); $t->integer('zone_manager_id'); });
        Schema::create('josya_users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('block')->default(false); });
        Schema::create('sys_pm_tenants_payments_invoices', function (Blueprint $t) { $t->id(); $t->integer('invoice_id'); $t->integer('payment_id'); $t->decimal('amount_allocated'); });
        (require database_path('migrations/2026_11_18_100000_create_tenant_reviews.php'))->up();
        foreach ([1 => 'Field Officer', 2 => 'Zone Manager', 3 => 'Assistant Internal Auditor', 4 => 'Accountant'] as $id => $name) {
            DB::table('josya_users')->insert(['id' => $id, 'name' => $name, 'block' => false]);
            DB::table('cb_roles')->insert(['id' => $id, 'name' => $name, 'display_name' => $name]);
            DB::table('cb_role_user')->insert(['user_id' => $id, 'role_id' => $id, 'user_type' => User::class]);
            DB::table('sys_user_staff_links')->insert(['user_id' => $id, 'staff_id' => $id * 10]);
        }
        DB::table('cb_permissions')->insert(['id' => 1, 'name' => 'update_approval_workflows']);
        DB::table('cb_permissions')->insert([['id' => 2, 'name' => 'create_tenants'], ['id' => 3, 'name' => 'update_tenants']]);
        DB::table('cb_permission_role')->insert([['permission_id' => 2, 'role_id' => 1], ['permission_id' => 3, 'role_id' => 1]]);
        DB::table('cb_permission_role')->insert(['permission_id' => 1, 'role_id' => 4]);
        (require database_path('migrations/2026_11_18_110000_version_tenant_review_workflows.php'))->up();
        (require database_path('migrations/2026_11_20_140000_register_tenant_onboarding_access.php'))->up();
        DB::table('sys_set_zones')->insert(['id' => 1, 'zone_manager_id' => 20]);
        Property::withoutEvents(fn () => Property::create(['id' => 1, 'zone_id' => 1, 'field_officer_id' => 10]));
        // The model does not mass assign its primary key.
        DB::table('sys_pm_properties')->where('id', 1)->update(['zone_id' => 1, 'field_officer_id' => 10]);
        Unit::create(['property_id' => 1, 'occupancy_status_id' => 38]);
        Client::create(['names' => 'Test tenant', 'property_id' => 1, 'unit_id' => 1, 'current_status_id' => 1]);
        ServiceAgreement::create(['property_id' => 1, 'current_status_id' => 5]);
        LeaseAgreement::create(['tenant_id' => 1, 'unit_id' => 1, 'property_id' => 1, 'sla_id' => 1, 'current_status_id' => 1, 'lease_status' => 'Active']);
        $this->reviews = app(TenantReviewService::class);
    }

    private function user(int $id): User { return User::findOrFail($id); }

    private function existingUnitUpload(): array
    {
        foreach (['2026_10_04_000010_create_temp_upload_batches_table.php', '2026_10_04_000011_create_temp_batch_units_table.php',
            '2026_10_04_000012_create_temp_batch_tenants_table.php', '2026_10_07_000002_add_lease_end_date_to_temp_batch_tenants.php',
            '2026_11_19_120000_add_mode_to_temp_upload_batches.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        $lookups = \App\Services\UnitTenantUpload\UnitTenantUploadLookups::fromArray([]);
        $batches = new \App\Services\UnitTenantUpload\UnitTenantUploadBatchService(
            new \App\Services\UnitTenantUpload\UnitTenantUploadValidator($lookups), $lookups
        );
        $submitter = new \App\Services\UnitTenantUpload\UnitTenantSubmitService($batches, $lookups, app(\App\Services\ServiceAgreementApprovalService::class));
        $unit = Unit::create(['property_id' => 1, 'unit_number' => 'A2', 'unit_code' => 'TEST-A2',
            'rent_amount' => 26796, 'vat_able' => 'Yes', 'occupancy_status_id' => 31]);
        $tenant = array_fill_keys(array_keys(\App\Services\UnitTenantUpload\UnitTenantUploadColumns::INDIVIDUAL), null);
        $tenant = array_replace($tenant, ['row_no' => 2, 'unit_number' => 'A2', 'names' => 'Upload test',
            'mobile_no' => '0712345678', 'national_id' => 'UPLOAD-TEST-123', 'lease_start_date' => '2026-12-01']);
        $parsed = ['units' => [['row_no' => 2, 'unit_number' => 'A2']], 'individual' => [$tenant], 'corporate' => []];
        return [$batches, $submitter, $unit, $parsed];
    }

    public function test_existing_unit_upload_creates_pending_tenant_and_lease_without_duplicating_or_repricing_unit(): void
    {
        [$batches, $submitter, $unit, $parsed] = $this->existingUnitUpload();
        $batch = $batches->stage(Property::find(1), $parsed, 'existing.xlsx', 1, 'existing_units');
        $this->assertSame('existing_units', $batch->mode);
        $this->assertSame($unit->id, (int) $batch->units()->sole()->unit_id);
        $this->actingAs($this->user(1));
        $this->assertSame(['submitted' => 1, 'failed' => 0], $submitter->submit($batch, 1));
        $this->assertSame(2, Unit::count());
        $this->assertSame(26796.0, (float) $unit->fresh()->rent_amount);
        $this->assertSame('Yes', $unit->fresh()->vat_able);
        $review = TenantReview::sole();
        $this->assertSame($unit->id, (int) $review->lease->unit_id);
        $this->assertSame(26796.0, (float) $review->tenant->rent_amount);
        $this->assertSame('zone_manager', $review->stage);
        $this->assertSame(1, (int) $review->tenant->current_status_id);
        $this->expectException(\DomainException::class);
        $submitter->submit($batch->fresh(), 1);
    }

    public function test_existing_unit_upload_rechecks_occupancy_at_submission(): void
    {
        [$batches, $submitter, $unit, $parsed] = $this->existingUnitUpload();
        $batch = $batches->stage(Property::find(1), $parsed, 'existing.xlsx', 1, 'existing_units');
        LeaseAgreement::create(['unit_id' => $unit->id, 'property_id' => 1, 'tenant_id' => 1, 'current_status_id' => 1]);
        $this->assertSame(['submitted' => 0, 'failed' => 1], $submitter->submit($batch, 1));
        $this->assertSame(1, Client::withoutGlobalScopes()->count());
        $this->assertStringContainsString('pending onboarding', $batch->units()->sole()->error);
    }

    public function test_existing_unit_template_lists_available_units_and_round_trips_only_filled_tenants(): void
    {
        $this->existingUnitUpload();
        foreach ([new \App\Models\UnitType, new \App\Models\UsageType, new \App\Models\UnitTopology,
            new \App\Models\TenantType, new \App\Models\UnitCategory, new \App\Models\SystemCountry,
            new \App\Models\Gender, new \App\Models\Salutation] as $model) {
            Schema::create($model->getTable(), function (Blueprint $t) {
                $t->id();
                $t->softDeletes();
                foreach (['name', 'type_name', 'topology_name', 'slug', 'category_name', 'country_name'] as $column) $t->string($column)->nullable();
            });
        }
        $service = new \App\Services\UnitTenantUpload\UnitTenantUploadTemplateService;
        $book = $service->build(Property::find(1), true);
        $units = $book->getSheetByName('units');
        $this->assertSame('unit_number', $units->getCell('A1')->getValue());
        $this->assertSame('unit_code', $units->getCell('B1')->getValue());
        $this->assertSame('A2', $units->getCell('A2')->getValue());
        $this->assertSame('B', $units->getHighestColumn());
        $this->assertTrue($units->getProtection()->getSheet());
        $sheet = $book->getSheetByName('individual_tenants');
        $this->assertSame('FFFFFF00', $sheet->getStyle('F1')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFFFFF00', $sheet->getStyle('G1')->getFill()->getStartColor()->getARGB());
        $sheet->setCellValue('C2', '2026-12-01')->setCellValue('D2', '0712345678')
            ->setCellValue('E2', 'Template test')->setCellValue('J2', 'TEMPLATE-123');
        $path = tempnam(sys_get_temp_dir(), 'existing-upload-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $parser = new \App\Services\UnitTenantUpload\UnitTenantUploadParserService;
            $parsed = $parser->parse($path, true, 1);
            $this->assertCount(1, $parsed['units']);
            $this->assertCount(1, $parsed['individual']);
            $this->assertSame([], $parsed['corporate']);
            $this->assertArrayNotHasKey('rent_amount', $parsed['units'][0]);
            try {
                $parser->parse($path, true, 999);
                $this->fail('A template for another property must be refused.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('different property or upload mode', $e->getMessage());
            }
        } finally {
            unlink($path);
        }
        $new = $service->build(Property::find(1));
        $this->assertSame('No', $new->getSheetByName('lists')->getCell('I2')->getValue());
        $this->assertSame('Yes', $new->getSheetByName('lists')->getCell('I3')->getValue());
    }

    public function test_existing_unit_upload_blocks_wrong_property_and_occupied_units_and_duplicate_staging(): void
    {
        [$batches, , $unit, $parsed] = $this->existingUnitUpload();
        $unit->update(['property_id' => 999]);
        $this->assertNotEmpty($batches->validateUpload(Property::find(1), $parsed, 'existing_units'));
        $unit->update(['property_id' => 1]);
        Client::withoutGlobalScopes()->where('id', 1)->update(['unit_id' => $unit->id, 'type_id' => 2]);
        $this->assertNotEmpty($batches->validateUpload(Property::find(1), $parsed, 'existing_units'));
        Client::withoutGlobalScopes()->where('id', 1)->update(['unit_id' => 1]);
        $batch = $batches->stage(Property::find(1), $parsed, 'existing.xlsx', 1, 'existing_units');
        $this->assertNotEmpty($batches->validateUpload(Property::find(1), $parsed, 'existing_units'));
        $this->expectException(\DomainException::class);
        $batches->updateUnit($batch->units()->sole(), ['rent_amount' => '1']);
    }

    public function test_existing_unit_upload_pins_original_unit_and_deleting_staged_tenant_keeps_real_unit(): void
    {
        [$batches, , $unit, $parsed] = $this->existingUnitUpload();
        $batch = $batches->stage(Property::find(1), $parsed, 'existing.xlsx', 1, 'existing_units');
        $unit->update(['unit_number' => 'RENAMED']);
        $batches->revalidate($batch);
        $this->assertNotNull($batch->units()->sole()->error);
        $batches->deleteTenant($batch->tenants()->sole());
        $this->assertSame(0, $batch->units()->count());
        $this->assertSame('RENAMED', $unit->fresh()->unit_number);
        $this->assertSame(26796.0, (float) $unit->fresh()->rent_amount);
    }
    private function review(): TenantReview
    {
        return DB::transaction(fn () => $this->reviews->register(Client::withoutGlobalScopes()->findOrFail(1), LeaseAgreement::findOrFail(1), $this->user(1)));
    }
    private function decide(TenantReview $review, int $actor, string $action = 'approve', ?string $reason = null): void
    {
        $review->refresh();
        $this->reviews->decide($review, $this->user($actor), $review->revision, $review->stage, $action, $reason);
    }

    public function test_preparation_signs_once_and_final_approval_approves_the_existing_records(): void
    {
        $review = $this->review();
        $this->assertSame('zone_manager', $review->stage);
        $this->assertSame('saved_and_signed', $review->events()->first()->action);
        $this->decide($review, 2);
        $this->assertSame(1, (int) LeaseAgreement::find(1)->current_status_id);
        $this->assertSame('assistant_internal_auditor', $review->fresh()->stage);
        $this->decide($review, 3);
        $this->assertSame('approved', $review->fresh()->stage);
        $this->assertSame(5, (int) LeaseAgreement::find(1)->current_status_id);
        $this->assertSame(5, (int) Client::withoutGlobalScopes()->find(1)->current_status_id);
        $this->assertSame(1, Client::withoutGlobalScopes()->count());
        $this->assertSame(1, LeaseAgreement::count());
    }

    public function test_auditor_cannot_skip_zone_manager_and_preparer_cannot_approve(): void
    {
        $review = $this->review();
        $this->assertFalse($this->reviews->reviewer($review, $this->user(3)));
        $this->assertFalse($this->reviews->reviewer($review, $this->user(1)));
        $this->expectException(HttpException::class);
        $this->decide($review, 3);
    }

    public function test_corrections_restart_at_zone_manager_and_old_decisions_are_refused(): void
    {
        $review = $this->review();
        $this->decide($review, 2);
        $this->decide($review, 3, 'return', 'Correct the passport.');
        DB::transaction(function () use ($review) {
            $locked = $this->reviews->lockForCorrection(1, $this->user(1));
            $this->assertSame('correction', $locked->stage);
            $this->reviews->resubmit($locked, $this->user(1));
        });
        $this->assertSame('zone_manager', $review->fresh()->stage);
        $this->assertSame(2, $review->fresh()->revision);
        $this->expectException(ValidationException::class);
        $this->reviews->decide($review, $this->user(2), 1, 'zone_manager', 'approve', null);
    }

    public function test_rejection_reverses_unpaid_invoice_and_releases_the_unit_with_history(): void
    {
        $review = $this->review();
        $invoice = DebtorInvoice::create(['lease_id' => 1, 'tenant_id' => 1, 'total_amount' => 20000, 'current_balance' => 20000, 'current_status_id' => 1]);
        TenantAccountTransaction::create(['invoice_id' => $invoice->id, 'tenant_id' => 1, 'dr_cr' => 'DR', 'debit_amount' => 20000, 'credit_amount' => 0]);
        Deposit::create(['invoice_id' => $invoice->id, 'amount' => 20000, 'invoice_balance' => 20000]);
        $this->decide($review, 2, 'reject', 'Application withdrawn.');
        $this->assertSame('rejected', $review->fresh()->stage);
        $this->assertFalse($review->fresh()->accounts_pending);
        $this->assertSame(3, (int) $invoice->fresh()->current_status_id);
        $this->assertSame(0.0, (float) $invoice->fresh()->current_balance);
        $this->assertSame(20000.0, (float) TenantAccountTransaction::where('dr_cr', 'CR')->sum('credit_amount'));
        $this->assertTrue(Unit::find(1)->isVacant());
        $this->assertSame(41, (int) Client::withoutGlobalScopes()->find(1)->current_status_id);
        $this->assertSame(2, $review->events()->count());
    }

    public function test_paid_invoice_is_preserved_and_marked_for_accounts_reconciliation(): void
    {
        $review = $this->review();
        $invoice = DebtorInvoice::create(['lease_id' => 1, 'total_amount' => 20000, 'current_balance' => 15000, 'current_status_id' => 8]);
        DB::table('sys_pm_tenants_payments_invoices')->insert(['invoice_id' => $invoice->id, 'payment_id' => 1, 'amount_allocated' => 5000]);
        $this->decide($review, 2, 'reject', 'Withdrawn after part payment.');
        $this->assertTrue($review->fresh()->accounts_pending);
        $this->assertSame(15000.0, (float) $invoice->fresh()->current_balance);
        $this->assertSame(8, (int) $invoice->fresh()->current_status_id);
        $this->assertSame(0, TenantAccountTransaction::count());
        $this->assertTrue($this->reviews->accounts($this->user(4)));
    }

    public function test_new_approvals_are_blocked_on_old_paths_but_legacy_records_are_allowed(): void
    {
        TenantReviewService::assertLegacy(1, 'lease');
        $this->review();
        $this->expectException(ValidationException::class);
        TenantReviewService::assertLegacy(1, 'lease');
    }

    public function test_final_approval_requires_an_approved_sla_and_rejection_requires_a_reason(): void
    {
        $review = $this->review();
        try {
            $this->decide($review, 2, 'reject');
            $this->fail('A rejection reason is required.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
        $this->decide($review, 2);
        ServiceAgreement::where('id', 1)->update(['current_status_id' => 1]);
        $this->expectException(ValidationException::class);
        $this->decide($review, 3);
    }

    public function test_other_field_officers_and_zone_managers_cannot_edit_or_review_this_tenant(): void
    {
        $review = $this->review();
        DB::table('sys_user_staff_links')->where('user_id', 2)->update(['staff_id' => 999]);
        $this->assertFalse($this->reviews->reviewer($review, $this->user(2)));
        $this->assertFalse($this->reviews->canView($review, $this->user(2)));
        $this->expectException(HttpException::class);
        $this->reviews->lockForCorrection(1, $this->user(1));
    }

    public function test_legacy_confirmation_http_endpoints_cannot_skip_the_stages(): void
    {
        $this->review();
        $this->withoutMiddleware()->actingAs($this->user(3));
        foreach (['Clients', 'Tenants', 'Lease Agreements'] as $section) {
            $this->postJson('/property/confirmations/1/approve', ['section' => $section])->assertStatus(422);
            $this->postJson('/property/confirmations/1/reject', ['section' => $section, 'reason' => 'No'])->assertStatus(422);
            $this->postJson('/property/confirmations/bulk-approve', ['section' => $section, 'ids' => [1]])->assertStatus(422);
            $this->postJson('/property/confirmations/bulk-reject', ['section' => $section, 'ids' => [1], 'reason' => 'No'])->assertStatus(422);
        }
        $this->assertSame(1, (int) LeaseAgreement::find(1)->current_status_id);
        $this->assertSame('zone_manager', TenantReview::first()->stage);
    }

    public function test_posted_invoice_is_reversed_with_a_balanced_journal_and_repeated_rejection_is_refused(): void
    {
        Schema::create('sys_gl_accounts_setup', function (Blueprint $t) { $t->id(); $t->integer('active_fiscal_year_id'); });
        Schema::create('sys_gl_transactions', function (Blueprint $t) {
            $t->id();
            foreach (['date_time', 'journal_date', 'reference', 'currency_id', 'accounting_period_id', 'trans_details', 'current_status_id', 'trans_type_id', 'entered_by'] as $name) $t->text($name)->nullable();
        });
        Schema::create('sys_gl_transactions_91_repeat', function (Blueprint $t) {
            $t->id(); $t->integer('parent_id'); $t->integer('account_id'); $t->string('entry_type'); $t->decimal('amount'); $t->string('narration')->nullable();
        });
        DB::table('sys_gl_accounts_setup')->insert(['active_fiscal_year_id' => 1]);
        $invoice = DebtorInvoice::create(['lease_id' => 1, 'total_amount' => 20000, 'current_balance' => 20000, 'current_status_id' => 1]);
        $header = DB::table('sys_gl_transactions')->insertGetId(['trans_details' => 'invoice_id:'.$invoice->id.':invoice']);
        DB::table('sys_gl_transactions_91_repeat')->insert([
            ['parent_id' => $header, 'account_id' => 1, 'entry_type' => 'Debit', 'amount' => 20000],
            ['parent_id' => $header, 'account_id' => 2, 'entry_type' => 'Credit', 'amount' => -20000],
        ]);
        $review = $this->review();
        $this->decide($review, 2, 'reject', 'Withdrawn.');
        $reversal = DB::table('sys_gl_transactions')->where('trans_details', 'tenant-review-reversal:invoice:'.$invoice->id)->value('id');
        $this->assertNotNull($reversal);
        $this->assertSame(0.0, (float) DB::table('sys_gl_transactions_91_repeat')->where('parent_id', $reversal)->sum('amount'));
        $this->assertSame(-20000.0, (float) DB::table('sys_gl_transactions_91_repeat')->where('parent_id', $reversal)->where('account_id', 1)->value('amount'));
        try {
            $this->reviews->decide($review, $this->user(2), 1, 'zone_manager', 'reject', 'Again.');
            $this->fail('A repeated decision must fail.');
        } catch (ValidationException $e) {
            $this->assertSame(2, DB::table('sys_gl_transactions')->count());
        }
    }

    public function test_add_tenant_save_bills_the_deposit_and_creates_a_pending_review(): void
    {
        config(['accounting.post_invoice_journals' => false]);
        $unit = Unit::create(['property_id' => 1, 'rent_amount' => 25000, 'unit_code' => 'TEST-2', 'vat_able' => 'No']);
        \App\Models\InvoiceableItem::create(['item_name' => 'House Deposit', 'billing_code' => '2002', 'commissionable' => 'No']);
        $this->withoutMiddleware()->actingAs($this->user(1));
        $response = $this->post('/units/tenants', [
            'property_id' => 1, 'unit_id' => $unit->id, 'lease_start_date' => '2026-12-01',
            'lease_years' => 1, 'frequency' => 'Annually', 'tenant_type' => 3,
            'company_name' => 'TEST COMPANY', 'mobile_number' => '254712345678',
            'registered_date' => '2026-11-18', 'deposit_months' => 2, 'deposit_held_by' => 'Company',
        ]);
        $response->assertSessionHasNoErrors()->assertSessionMissing('error');
        $review = TenantReview::sole();
        $response->assertRedirect(route('tenant-reviews.show', $review));
        $this->assertSame('zone_manager', $review->stage);
        $this->assertSame(1, (int) $review->tenant->current_status_id);
        $this->assertSame('+254712345678', $review->tenant->mobile_number);
        $this->assertSame(1, (int) $review->lease->current_status_id);
        $invoice = DebtorInvoice::sole();
        $this->assertSame(50000.0, (float) $invoice->total_amount);
        $this->assertSame(50000.0, (float) TenantAccountTransaction::sole()->debit_amount);
        $this->assertSame((int) $review->lease_id, (int) Deposit::sole()->lease_id);
    }

    public function test_individual_onboarding_accepts_either_identity_document_and_saves_an_international_phone(): void
    {
        $this->withoutMiddleware()->actingAs($this->user(1));

        foreach ([
            ['national_id', '12345678', 'NATIONAL_ID', '+254 712 345 678', '+254712345678'],
            ['passport_number', 'P1234567', 'PASSPORT', '+256-772-123-456', '+256772123456'],
        ] as [$field, $number, $documentType, $phone, $savedPhone]) {
            $unit = Unit::create(['property_id' => 1, 'rent_amount' => 25000, 'unit_code' => $documentType, 'vat_able' => 'No']);
            $this->postJson('/units/tenants', [
                'property_id' => 1, 'unit_id' => $unit->id, 'lease_start_date' => '2026-12-01',
                'lease_years' => 1, 'frequency' => 'Annually', 'tenant_type' => 1,
                'names' => 'Test tenant', 'nationality_id' => 1, 'registered_date' => '2026-11-18',
                $field => $number, 'mobile_number' => $phone,
            ])->assertOk()->assertJsonPath('success', true);

            $this->assertDatabaseHas('cb_tenant_records', [
                'id_document_type' => $documentType, $field => $number, 'phone' => $savedPhone,
            ]);
        }
    }

    public function test_individual_onboarding_rejects_missing_identification_and_phone_without_country_code(): void
    {
        $this->withoutMiddleware()->actingAs($this->user(1));
        $unit = Unit::create(['property_id' => 1, 'rent_amount' => 25000, 'unit_code' => 'TEST-2', 'vat_able' => 'No']);
        $details = [
            'property_id' => 1, 'unit_id' => $unit->id, 'lease_start_date' => '2026-12-01',
            'lease_years' => 1, 'frequency' => 'Annually', 'tenant_type' => 1,
            'names' => 'Test tenant', 'nationality_id' => 1, 'registered_date' => '2026-11-18',
        ];

        $this->postJson('/units/tenants', $details + ['mobile_number' => '+254712345678'])
            ->assertUnprocessable()->assertJsonValidationErrors('national_id');
        $this->postJson('/units/tenants', $details + ['national_id' => '12345678', 'mobile_number' => '0712345678'])
            ->assertUnprocessable()->assertJsonValidationErrors('mobile_number');
        $this->assertSame(0, TenantReview::count());
    }

    public function test_next_reviewer_gets_an_in_app_notification(): void
    {
        $review = $this->review();
        $this->assertTrue(\App\Models\Notification::where('notifiable_id', 2)->where('type', 'tenant_review')->exists());
        $this->decide($review, 2);
        $this->assertTrue(\App\Models\Notification::where('notifiable_id', 3)->where('type', 'tenant_review')->exists());
    }

    public function test_a_bulk_posted_invoice_is_kept_for_accounts_to_reconcile(): void
    {
        Schema::create('sys_gl_transactions', function (Blueprint $t) { $t->id(); $t->string('trans_details'); });
        DB::table('sys_gl_transactions')->insert(['trans_details' => 'batch:B1:property:1']);
        $invoice = DebtorInvoice::create(['lease_id' => 1, 'property_id' => 1, 'batch_number' => 'B1',
            'total_amount' => 20000, 'current_balance' => 20000, 'current_status_id' => 1]);
        $review = $this->review();
        $this->decide($review, 2, 'reject', 'Withdrawn.');
        $this->assertTrue($review->fresh()->accounts_pending);
        $this->assertSame(20000.0, (float) $invoice->fresh()->current_balance);
        $this->assertSame(1, DB::table('sys_gl_transactions')->count());
    }

    public function test_review_page_shows_the_correct_action_for_each_level_and_for_corrections(): void
    {
        $review = $this->review();
        $review->tenant->setRelation('documents', collect())->setRelation('tenantBankDetails', collect())->setRelation('tenantNextOfKin', collect());
        $source = preg_replace('/^@extends.*\R/', '', file_get_contents(resource_path('views/property/tenant-reviews/show.blade.php')), 1);
        $render = function (bool $canAct, bool $canCorrect) use ($source, $review) {
            return \Illuminate\Support\Facades\Blade::render($source."\n@yield('content')", [
                'review' => $review, 'canAct' => $canAct, 'canCorrect' => $canCorrect, 'canAccounts' => false,
                'invoices' => collect(), 'inspections' => collect(), 'errors' => new \Illuminate\Support\ViewErrorBag(),
            ], true);
        };
        $html = $render(true, false);
        $this->assertStringContainsString('Sign and send to Assistant Internal Auditor', $html);
        $this->assertStringContainsString('Return to Field Officer', $html);
        $review->stage = 'assistant_internal_auditor';
        $this->assertStringContainsString('Approve tenant and lease', $render(true, false));
        $review->stage = 'correction';
        $html = $render(false, true);
        $this->assertStringContainsString('Correct tenant details', $html);
        $this->assertStringNotContainsString('name="action" value="approve"', $html);
    }

    private function anotherReview(): TenantReview
    {
        $unit = Unit::create(['property_id' => 1, 'occupancy_status_id' => 38]);
        $tenant = Client::create(['property_id' => 1, 'unit_id' => $unit->id, 'current_status_id' => 1]);
        $lease = LeaseAgreement::create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'property_id' => 1,
            'sla_id' => 1, 'current_status_id' => 1, 'lease_status' => 'Active']);
        return DB::transaction(fn () => $this->reviews->register($tenant, $lease, $this->user(1)));
    }

    public function test_publishing_a_shorter_workflow_changes_new_reviews_and_preserves_existing_corrections(): void
    {
        $old = $this->review();
        $workflows = app(\App\Services\TenantReviewWorkflowService::class);
        $version = $workflows->publish([3], $old->workflow_version_id, $this->user(4));
        $new = $this->anotherReview();
        $this->assertSame((int) $version->id, (int) $new->workflow_version_id);
        $this->assertSame('approval_1', $new->stage);
        $this->assertTrue($this->reviews->reviewer($new, $this->user(3)));
        $this->assertFalse($this->reviews->reviewer($new, $this->user(2)));
        $this->decide($new, 3);
        $this->assertSame('approved', $new->fresh()->stage);
        $this->decide($old, 2, 'return', 'Correct the ID.');
        DB::transaction(function () use ($old) {
            $locked = $this->reviews->lockForCorrection($old->tenant_id, $this->user(1));
            $this->reviews->resubmit($locked, $this->user(1));
        });
        $this->assertSame('zone_manager', $old->fresh()->stage);
        $this->assertNotEquals($version->id, $old->fresh()->workflow_version_id);
    }

    public function test_added_steps_need_separate_signatures_before_the_final_approval(): void
    {
        DB::table('cb_permission_role')->insert(['role_id' => 4, 'permission_id' => DB::table('cb_permissions')->where('name', 'review_tenant_onboarding')->value('id')]);
        $workflows = app(\App\Services\TenantReviewWorkflowService::class);
        $workflows->publish([2, 4, 3], $workflows->current()->id, $this->user(4));
        $review = $this->review();
        $this->decide($review, 2);
        $this->assertSame('approval_2', $review->fresh()->stage);
        $this->assertFalse($this->reviews->reviewer($review->fresh(), $this->user(3)));
        $this->decide($review, 4);
        $this->assertSame(1, (int) LeaseAgreement::find(1)->current_status_id);
        $this->decide($review, 3);
        $this->assertSame('approved', $review->fresh()->stage);
    }

    public function test_publishing_requires_setup_permission_and_the_current_version(): void
    {
        $workflows = app(\App\Services\TenantReviewWorkflowService::class);
        $this->assertFalse($workflows->canManage($this->user(2)));
        $this->assertTrue($workflows->canManage($this->user(4)));
        $oldVersion = $workflows->current()->id;
        $workflows->publish([3, 2], $oldVersion, $this->user(4));
        try {
            $workflows->publish([3], $oldVersion, $this->user(4));
            $this->fail('Stale setup publication should be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('version', $e->errors());
        }
        $this->expectException(HttpException::class);
        $workflows->publish([3], $workflows->current()->id, $this->user(2));
    }

    public function test_reordered_steps_follow_the_published_order(): void
    {
        $workflows = app(\App\Services\TenantReviewWorkflowService::class);
        $workflows->publish([3, 2], $workflows->current()->id, $this->user(4));
        $review = $this->review();
        $this->assertTrue($this->reviews->reviewer($review, $this->user(3)));
        $this->assertFalse($this->reviews->reviewer($review, $this->user(2)));
        $this->decide($review, 3);
        $this->assertTrue($this->reviews->reviewer($review->fresh(), $this->user(2)));
        $this->decide($review, 2);
        $this->assertSame('approved', $review->fresh()->stage);
    }

    public function test_setup_routes_enforce_permission_and_show_a_versioned_editor(): void
    {
        $this->withoutMiddleware()->actingAs($this->user(2));
        $this->getJson('/approvals/setup')->assertForbidden();
        $this->postJson('/approvals/setup/tenant-onboarding', ['version' => 1, 'steps' => [['role_id' => 3]]])->assertForbidden();
        $this->actingAs($this->user(4));
        $this->post('/approvals/setup/tenant-onboarding', ['version' => 1, 'steps' => [['role_id' => 3]]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $version = app(\App\Services\TenantReviewWorkflowService::class)->current();
        $this->assertSame(2, $version->number);
        $source = preg_replace('/^@extends.*\R/', '', file_get_contents(resource_path('views/property/tenant-reviews/setup.blade.php')), 1);
        $html = \Illuminate\Support\Facades\Blade::render($source."\n@yield('content')", [
            'version' => $version, 'roles' => DB::table('cb_roles')->get(),
            'history' => \App\Models\TenantReviewWorkflowVersion::with('author')->get(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
        ], true);
        $this->assertStringContainsString('Publish New Version', $html);
        $this->assertStringContainsString('Move up', $html);
        $this->assertStringContainsString('Remove step', $html);
        $this->assertStringContainsString('steps[0][role_id]', $html);
    }

    public function test_setup_rejects_an_empty_sequence_and_an_unknown_role(): void
    {
        $workflows = app(\App\Services\TenantReviewWorkflowService::class);
        foreach ([[], [9999]] as $roles) {
            try {
                $workflows->publish($roles, $workflows->current()->id, $this->user(4));
                $this->fail('An invalid setup must fail.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('steps', $e->errors());
            }
        }
        $this->assertSame(1, \App\Models\TenantReviewWorkflowVersion::count());
    }

    public function test_onboarding_menus_and_permissions_are_registered_without_duplicate_grants(): void
    {
        (require database_path('migrations/2026_11_20_140000_register_tenant_onboarding_access.php'))->up();
        $this->assertSame(3, DB::table('cb_sub_modules')->count());
        $this->assertSame(6, DB::table('cb_permissions')->count());
        $this->assertSame(10, (int) DB::table('cb_permissions')->where('name', 'update_approval_workflows')->value('module_id'));
        $this->assertSame(6, DB::table('cb_sub_module_permissions')->count());
        $this->assertFalse(app(\App\Services\TenantReviewWorkflowService::class)->canManage($this->user(1)));
    }

    public function test_removing_review_permission_blocks_direct_decisions_but_keeps_view_access(): void
    {
        $review = $this->review();
        $this->assertTrue($this->reviews->reviewer($review, $this->user(2)));
        DB::table('cb_permission_role')->where('role_id', 2)->where('permission_id', DB::table('cb_permissions')->where('name', 'review_tenant_onboarding')->value('id'))->delete();
        $this->assertTrue($this->reviews->canView($review, $this->user(2)));
        $this->assertFalse($this->reviews->reviewer($review, $this->user(2)));
        $this->withoutMiddleware([\App\Http\Middleware\OneLogin::class, \App\Http\Middleware\EnsureStaffPasswordIsCurrent::class, \App\Http\Middleware\AccessChecker::class])->actingAs($this->user(2));
        $this->postJson('/approvals/new-tenants/'.$review->id.'/decision', ['revision' => 1, 'stage' => 'zone_manager', 'action' => 'approve'])->assertForbidden();
        $this->assertSame('zone_manager', $review->fresh()->stage);
    }

    public function test_removing_the_module_blocks_direct_links_and_post_requests(): void
    {
        $review = $this->review();
        DB::table('cb_sub_module_permissions')->where('role_id', 2)->delete();
        $this->assertFalse($this->reviews->canView($review, $this->user(2)));
        $this->withoutMiddleware([\App\Http\Middleware\OneLogin::class, \App\Http\Middleware\EnsureStaffPasswordIsCurrent::class, \App\Http\Middleware\AccessChecker::class])->actingAs($this->user(2));
        $this->getJson('/approvals/new-tenants')->assertForbidden();
        $this->getJson('/approvals/new-tenants/'.$review->id)->assertForbidden();
        $this->postJson('/approvals/new-tenants/'.$review->id.'/decision', ['revision' => 1, 'stage' => 'zone_manager', 'action' => 'approve'])->assertForbidden();
    }

    public function test_setup_module_and_accounts_permission_are_enforced_separately(): void
    {
        $this->assertTrue($this->reviews->canReconcile($this->user(4)));
        $accountsPermission = DB::table('cb_permissions')->where('name', 'reconcile_tenant_onboarding')->value('id');
        DB::table('cb_permission_role')->where('role_id', 4)->where('permission_id', $accountsPermission)->delete();
        $this->assertFalse($this->reviews->canReconcile($this->user(4)));
        $review = $this->review();
        $this->withoutMiddleware()->actingAs($this->user(4));
        $this->getJson('/approvals/new-tenants/reconciliation')->assertForbidden();
        $this->postJson('/approvals/new-tenants/'.$review->id.'/reconcile', ['revision' => 1, 'reason' => 'Test'])->assertForbidden();
        $setup = DB::table('cb_sub_modules')->where('sub_module_url', '/approvals/setup')->value('id');
        DB::table('cb_sub_module_permissions')->where('role_id', 4)->where('sub_module_id', $setup)->delete();
        $this->postJson('/approvals/setup/tenant-onboarding', ['version' => 1, 'steps' => [['role_id' => 3]]])->assertForbidden();
    }

    public function test_a_role_needs_module_and_permissions_before_being_added_to_a_workflow(): void
    {
        $workflows = app(\App\Services\TenantReviewWorkflowService::class);
        $this->expectException(ValidationException::class);
        $workflows->publish([2, 4, 3], $workflows->current()->id, $this->user(4));
    }

    public function test_granting_review_permission_does_not_let_the_preparer_approve_their_own_tenant(): void
    {
        DB::table('cb_permission_role')->insert(['role_id' => 1, 'permission_id' => DB::table('cb_permissions')->where('name', 'review_tenant_onboarding')->value('id')]);
        $review = $this->review();
        $this->assertTrue(\App\Services\TenantReviewAccess::allows($this->user(1), 'review_tenant_onboarding'));
        $this->assertFalse($this->reviews->reviewer($review, $this->user(1)));
        $this->assertFalse($this->reviews->reviewer($review, $this->user(3)));
    }

    public function test_revoking_view_permission_stops_review_access_even_when_the_module_remains_checked(): void
    {
        $review = $this->review();
        DB::table('cb_permission_role')->where('role_id', 2)->where('permission_id', DB::table('cb_permissions')->where('name', 'view_tenant_onboarding')->value('id'))->delete();
        $this->assertTrue(\App\Services\TenantReviewAccess::roleHasPage(2, '/approvals/new-tenants'));
        $this->assertFalse($this->reviews->canView($review, $this->user(2)));
        $this->assertFalse($this->reviews->reviewer($review, $this->user(2)));
    }

    public function test_standard_role_screens_list_the_registered_onboarding_modules_and_permissions(): void
    {
        $values = ['role' => DB::table('cb_roles')->where('id', 1)->first(),
            'modules' => \App\Models\Module::with('submodules')->get(), 'assigned' => [],
            'permissions' => DB::table('cb_permissions')->get(), 'errors' => new \Illuminate\Support\ViewErrorBag()];
        foreach (['modulesaccess' => ['New Tenant Approvals', 'Approval Setup', 'Rejected Tenant Reconciliation'],
            'permissionsaccess' => ['view_tenant_onboarding', 'review_tenant_onboarding', 'reconcile_tenant_onboarding', 'update_approval_workflows']] as $view => $labels) {
            $source = str_replace("@extends('layouts.main')", '', file_get_contents(resource_path('views/roles/'.$view.'.blade.php')));
            $html = \Illuminate\Support\Facades\Blade::render($source."\n@yield('content')", $values, true);
            $this->assertStringContainsString('Approval Control', $html);
            foreach ($labels as $label) $this->assertStringContainsString($label, $html);
        }
    }

    public function test_the_existing_tenant_creation_and_edit_permissions_are_still_required(): void
    {
        $review = $this->review();
        $this->decide($review, 2, 'return', 'Correct the details.');
        DB::table('cb_permission_role')->where('role_id', 1)->where('permission_id', 2)->delete();
        try {
            $this->reviews->assertPreparer(1, $this->user(1));
            $this->fail('Viewing onboarding must not grant tenant creation.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('create_tenants', $e->errors()['property_id'][0]);
        }
        DB::table('cb_permission_role')->where('role_id', 1)->where('permission_id', 3)->delete();
        $this->expectException(HttpException::class);
        $this->reviews->lockForCorrection(1, $this->user(1));
    }
}
