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
        Schema::create('cb_permissions', function (Blueprint $t) { $t->id(); $t->string('name'); });
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
        DB::table('cb_permission_role')->insert(['permission_id' => 1, 'role_id' => 4]);
        (require database_path('migrations/2026_11_18_110000_version_tenant_review_workflows.php'))->up();
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
        $this->assertSame(1, (int) $review->lease->current_status_id);
        $invoice = DebtorInvoice::sole();
        $this->assertSame(50000.0, (float) $invoice->total_amount);
        $this->assertSame(50000.0, (float) TenantAccountTransaction::sole()->debit_amount);
        $this->assertSame((int) $review->lease_id, (int) Deposit::sole()->lease_id);
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
}
