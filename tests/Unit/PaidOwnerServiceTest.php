<?php

namespace Tests\Unit;

use App\Exceptions\ClientAccountException;
use App\Http\Controllers\RecoveryController;
use App\Models\Recovery;
use App\Services\GlJournalPoster;
use App\Services\PaidOwnerService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * A tenant pays the landlord directly (Paid Owner). The field officer records it, the confirmer role confirms it, and
 * confirming pays the invoice down, credits the tenant ledger and debits the client ledger, all or nothing.
 */
class PaidOwnerServiceTest extends TestCase
{
    const CREATOR = 11;
    const CONFIRMER = 10;
    const OUTSIDER = 12;
    const ROLE = 7;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sys_set_transactions_types', function ($table) {
            $table->increments('id');
            $table->string('type_name')->nullable();
            $table->integer('workflow_status_id')->nullable();
        });
        Schema::create('approval_workflows', function ($table) {
            $table->increments('id');
            $table->string('workflow_name');
        });
        Schema::create('workflow_approvers', function ($table) {
            $table->increments('id');
            $table->integer('workflow_id');
            $table->integer('approver_id')->nullable();
            $table->integer('approval_level')->default(1);
            $table->integer('is_final')->default(0);
            $table->integer('is_active')->default(1);
            $table->integer('added_by')->nullable();
            $table->dateTime('added_on')->nullable();
            $table->string('position_type')->nullable();
            $table->integer('role_id')->nullable();
        });
        Schema::create('workflow_approvers_changes_log', function ($table) {
            $table->increments('id');
            $table->integer('workflow_id');
            $table->integer('approver_id')->nullable();
            $table->string('action');
            $table->integer('old_level')->nullable();
            $table->integer('new_level')->nullable();
            $table->integer('old_is_final')->nullable();
            $table->integer('new_is_final')->nullable();
            $table->text('details')->nullable();
            $table->integer('changed_by')->nullable();
            $table->dateTime('changed_on')->nullable();
        });
        Schema::create('cb_roles', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('display_name')->nullable();
        });
        Schema::create('cb_role_user', function ($table) {
            $table->integer('role_id');
            $table->integer('user_id');
        });
        Schema::create('josya_users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        Schema::create('sys_pm_clients', function ($table) {
            $table->increments('id');
            $table->string('names')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('type_id')->nullable();
        });
        Schema::create('sys_pm_properties', function ($table) {
            $table->increments('id');
            $table->integer('client_id')->nullable();
            $table->integer('zone_id')->nullable();
            $table->string('reference_number')->nullable();
        });
        Schema::create('sys_pm_service_agreements', function ($table) {
            $table->increments('id');
            $table->integer('property_id')->nullable();
            $table->integer('client_d')->nullable();
            $table->date('date_start')->nullable();
        });
        Schema::create('sys_pm_lease_agreements', function ($table) {
            $table->increments('id');
            $table->integer('unit_id')->nullable();
            $table->integer('tenant_id')->nullable();
            $table->string('ref_number')->nullable();
            $table->string('lease_status')->nullable();
            $table->integer('current_status_id')->nullable();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
        });
        Schema::create('cb_tenant_records', function ($table) {
            $table->integer('id');
            $table->string('names')->nullable();
        });
        Schema::create('sys_pm_units', function ($table) {
            $table->increments('id');
            $table->string('unit_code')->nullable();
        });
        Schema::create('sys_pm_debtors_invoices', function ($table) {
            $table->increments('id');
            $table->string('reference_number')->nullable();
            $table->string('narration')->nullable();
            $table->string('debtor_type_id')->nullable();
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->decimal('current_balance', 19, 4)->nullable();
            $table->integer('current_status_id')->default(1);
            $table->integer('tenant_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->integer('lease_id')->nullable();
            $table->integer('sla_id')->nullable();
            $table->integer('zone_id')->nullable();
            $table->integer('field_officer_id')->nullable();
            $table->integer('zone_supervisor_id')->nullable();
            $table->integer('zone_manager_id')->nullable();
            $table->string('return_type_id')->nullable();
            $table->date('date_due')->nullable();
        });
        Schema::create('sys_pm_recoveries', function ($table) {
            $table->increments('id');
            $table->dateTime('date_time')->nullable();
            $table->string('ref_number')->nullable();
            $table->integer('type_id')->nullable();
            $table->date('recovery_date')->nullable();
            $table->text('narration')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('zone_id')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->integer('sla_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->integer('lease_id')->nullable();
            $table->integer('tenant_id')->nullable();
            $table->string('commisionable')->nullable();
            $table->integer('current_status_id')->default(1);
            $table->decimal('amount', 13, 2)->nullable();
            $table->integer('offset_invoice_id')->nullable();
            $table->dateTime('posted_to_landlord_at')->nullable();
            $table->integer('actioned_by')->nullable();
            $table->dateTime('actioned_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('proof_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();
            $table->integer('proof_uploaded_by')->nullable();
            $table->dateTime('proof_uploaded_at')->nullable();
        });
        Schema::create('sys_pm_tenants_accounts_trans', function ($table) {
            $table->increments('id');
            $table->dateTime('date_time')->nullable();
            $table->string('narration')->nullable();
            $table->string('pay_for')->nullable();
            $table->string('dr_cr')->nullable();
            $table->decimal('debit_amount', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);
            $table->decimal('receipt_number', 18, 0)->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('entry_ref')->nullable();
            $table->string('entry_by')->nullable();
            $table->string('invoice_number')->nullable();
            $table->integer('invoice_id')->nullable();
            $table->integer('payment_id')->nullable();
            $table->integer('zone_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->integer('lease_id')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('tenant_id')->nullable();
            $table->integer('sla_id')->nullable();
            $table->integer('field_officer_id')->nullable();
            $table->integer('zone_supervisor_id')->nullable();
            $table->integer('zone_manager_id')->nullable();
            $table->integer('current_status_id')->default(1);
        });
        Schema::create('cb_landlord_account', function ($table) {
            $table->increments('id');
            $table->integer('year')->nullable();
            $table->integer('month')->nullable();
            $table->string('landlord_code')->nullable();
            $table->string('landlord_name')->nullable();
            $table->string('property_code')->nullable();
            $table->string('transaction_number');
            $table->string('entry_type_code')->nullable();
            $table->string('entry_description')->nullable();
            $table->string('entry_particulars')->nullable();
            $table->dateTime('entry_date')->nullable();
            $table->decimal('debit_amount', 18, 2)->default(0);
            $table->decimal('credit_amount', 18, 0)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('gl_account')->nullable();
            $table->string('vendor_id')->nullable();
            $table->integer('is_recovered')->default(0);
            $table->integer('client_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->string('audit_user')->nullable();
            $table->string('return_code')->nullable();
        });
        Schema::create('sys_gl_accounts_setup', function ($table) {
            $table->increments('id');
            $table->integer('clients_account_id')->nullable();
            $table->integer('tenants_account_id')->nullable();
        });

        DB::table('sys_set_transactions_types')->insert(['id' => 34, 'type_name' => 'Paid Owner', 'workflow_status_id' => 37]);
        DB::table('approval_workflows')->insert(['id' => 1, 'workflow_name' => 'paid_owner_confirmation']);
        DB::table('workflow_approvers')->insert(['workflow_id' => 1, 'position_type' => 'role', 'role_id' => self::ROLE]);
        DB::table('cb_roles')->insert(['id' => self::ROLE, 'name' => 'auditor', 'display_name' => 'Auditor']);
        DB::table('cb_role_user')->insert(['role_id' => self::ROLE, 'user_id' => self::CONFIRMER]);
        DB::table('josya_users')->insert([['id' => self::CREATOR, 'name' => 'Field Officer'], ['id' => self::CONFIRMER, 'name' => 'Auditor One'], ['id' => self::OUTSIDER, 'name' => 'Someone Else']]);
        DB::table('sys_pm_clients')->insert([['id' => 100, 'names' => 'Landlord', 'reference_number' => 'LAN-00100'], ['id' => 200, 'names' => 'Tenant', 'reference_number' => 'TEN-00200']]);
        DB::table('sys_pm_properties')->insert(['id' => 300, 'client_id' => 100, 'zone_id' => 5, 'reference_number' => '300A']);
        DB::table('sys_pm_units')->insert(['id' => 400, 'unit_code' => 'A1']);
        DB::table('sys_pm_debtors_invoices')->insert([
            'id' => 500, 'reference_number' => 'INV500', 'narration' => 'RENT FOR July 2026', 'debtor_type_id' => '2',
            'total_amount' => 10000, 'current_balance' => 10000, 'current_status_id' => 1,
            'tenant_id' => 200, 'unit_id' => 400, 'property_id' => 300, 'zone_id' => 5,
        ]);
        DB::table('sys_gl_accounts_setup')->insert(['clients_account_id' => 1022, 'tenants_account_id' => 1023]);
    }

    /**
     * The service with a GL poster that must not be called unless a test says so.
     */
    protected function service(?GlJournalPoster $gl = null): PaidOwnerService
    {
        return new PaidOwnerService($gl ?? Mockery::mock(GlJournalPoster::class));
    }

    /**
     * Record a paid-owner recovery against invoice 500.
     */
    protected function recovery(float $amount = 4000, array $overrides = []): Recovery
    {
        return Recovery::create(array_merge([
            'ref_number' => 'PO-1', 'type_id' => 34, 'recovery_date' => '2026-07-20', 'amount' => $amount,
            'client_id' => 100, 'property_id' => 300, 'unit_id' => 400, 'tenant_id' => 200, 'offset_invoice_id' => 500,
            'commisionable' => 'Yes', 'current_status_id' => 1, 'created_by' => self::CREATOR, 'zone_id' => 5,
            'proof_path' => 'recovery_proofs/1/proof.pdf', 'proof_name' => 'proof.pdf',
        ], $overrides));
    }

    protected function invoice(): object
    {
        return DB::table('sys_pm_debtors_invoices')->where('id', 500)->first();
    }

    public function test_confirming_pays_the_invoice_down_credits_the_tenant_and_debits_the_client(): void
    {
        $recovery = $this->recovery(4000);

        $result = $this->service()->confirm($recovery, self::CONFIRMER);

        $this->assertSame(8, $result['invoice_status']);
        $this->assertEquals(6000.0, (float) $this->invoice()->current_balance);
        $this->assertSame(8, (int) $this->invoice()->current_status_id);

        $credit = DB::table('sys_pm_tenants_accounts_trans')->first();
        $this->assertSame('CR', $credit->dr_cr);
        $this->assertSame('PO', $credit->payment_mode);
        $this->assertEquals(4000.0, (float) $credit->credit_amount);
        $this->assertSame(500, (int) $credit->invoice_id);
        $this->assertSame(200, (int) $credit->tenant_id);

        $debit = DB::table('cb_landlord_account')->first();
        $this->assertSame('0007', $debit->entry_type_code);
        $this->assertEquals(4000.0, (float) $debit->debit_amount);
        $this->assertSame(0, (int) $debit->is_recovered);
        $this->assertNull($debit->return_code);
        $this->assertSame(100, (int) $debit->client_id);
        $this->assertSame('LAN-00100', $debit->landlord_code);

        $fresh = $recovery->fresh();
        $this->assertSame(5, (int) $fresh->current_status_id);
        $this->assertSame(self::CONFIRMER, (int) $fresh->actioned_by);
        $this->assertNotNull($fresh->posted_to_landlord_at);
    }

    public function test_paying_the_whole_invoice_clears_it_and_never_closes_it_as_paid_owner(): void
    {
        $this->service()->confirm($this->recovery(10000), self::CONFIRMER);

        // 9 = Cleared. Status 37 would drop the invoice out of the return (only statuses 1, 8 and 9 are returned)
        // while the client debit is still deducted, so the landlord would be docked twice.
        $this->assertSame(9, (int) $this->invoice()->current_status_id);
        $this->assertEquals(0.0, (float) $this->invoice()->current_balance);
    }

    public function test_the_person_who_recorded_it_cannot_confirm_it(): void
    {
        DB::table('cb_role_user')->insert(['role_id' => self::ROLE, 'user_id' => self::CREATOR]);

        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('someone else must confirm');

        $this->service()->confirm($this->recovery(), self::CREATOR);
    }

    public function test_someone_without_the_confirmer_role_cannot_confirm(): void
    {
        try {
            $this->service()->confirm($this->recovery(), self::OUTSIDER);
            $this->fail('A user without the role confirmed.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('Only Auditor can confirm', $e->getMessage());
        }

        $this->assertEquals(10000.0, (float) $this->invoice()->current_balance);
        $this->assertSame(0, DB::table('sys_pm_tenants_accounts_trans')->count());
        $this->assertSame(0, DB::table('cb_landlord_account')->count());
    }

    public function test_a_named_user_seat_can_confirm_too(): void
    {
        DB::table('workflow_approvers')->delete();
        DB::table('workflow_approvers')->insert(['workflow_id' => 1, 'approver_id' => self::OUTSIDER]);

        $this->service()->confirm($this->recovery(), self::OUTSIDER);

        $this->assertSame(1, DB::table('cb_landlord_account')->count());
    }

    public function test_nothing_can_be_confirmed_until_a_confirmer_is_set(): void
    {
        DB::table('workflow_approvers')->delete();

        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('No one is set to confirm');

        $this->service()->confirm($this->recovery(), self::CONFIRMER);
    }

    public function test_confirming_twice_does_not_post_twice(): void
    {
        $recovery = $this->recovery(4000);
        $this->service()->confirm($recovery, self::CONFIRMER);

        try {
            $this->service()->confirm($recovery, self::CONFIRMER);
            $this->fail('A confirmed recovery was confirmed again.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('no longer waiting', $e->getMessage());
        }

        $this->assertEquals(6000.0, (float) $this->invoice()->current_balance);
        $this->assertSame(1, DB::table('sys_pm_tenants_accounts_trans')->count());
        $this->assertSame(1, DB::table('cb_landlord_account')->count());
    }

    public function test_an_amount_that_no_longer_fits_the_invoice_is_refused_and_nothing_is_written(): void
    {
        $recovery = $this->recovery(8000);
        // The tenant paid the company in the meantime
        DB::table('sys_pm_debtors_invoices')->where('id', 500)->update(['current_balance' => 5000, 'current_status_id' => 8]);

        try {
            $this->service()->confirm($recovery, self::CONFIRMER);
            $this->fail('An amount over the balance was confirmed.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('more than what is left', $e->getMessage());
        }

        $this->assertEquals(5000.0, (float) $this->invoice()->current_balance);
        $this->assertSame(0, DB::table('sys_pm_tenants_accounts_trans')->count());
        $this->assertSame(1, (int) $recovery->fresh()->current_status_id);
    }

    public function test_a_failure_part_way_rolls_everything_back(): void
    {
        $recovery = $this->recovery(4000);
        // The client ledger step cannot find the client
        DB::table('sys_pm_clients')->where('id', 100)->delete();

        try {
            $this->service()->confirm($recovery, self::CONFIRMER);
            $this->fail('The confirm went through without the client.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('could not be found', $e->getMessage());
        }

        $this->assertEquals(10000.0, (float) $this->invoice()->current_balance);
        $this->assertSame(1, (int) $this->invoice()->current_status_id);
        $this->assertSame(0, DB::table('sys_pm_tenants_accounts_trans')->count());
        $this->assertSame(1, (int) $recovery->fresh()->current_status_id);
        $this->assertNull($recovery->fresh()->posted_to_landlord_at);
    }

    public function test_the_gl_gets_one_journal_when_posting_is_on(): void
    {
        config(['accounting.post_invoice_journals' => true]);
        $gl = Mockery::mock(GlJournalPoster::class);
        $gl->shouldReceive('exists')->once()->andReturn(false);
        $gl->shouldReceive('journal')->once()->withArgs(function ($reference, $narration, $details, $lines, $userId, $code) {
            return $reference === 'PO-1'
                && $details === 'recovery_id:1:paid_owner'
                && $lines === [
                    ['account_id' => 1022, 'type' => 'Debit', 'amount' => 4000.0],
                    ['account_id' => 1023, 'type' => 'Credit', 'amount' => 4000.0],
                ]
                && $userId === self::CONFIRMER
                && $code === '2032';
        })->andReturn(99);

        $this->service($gl)->confirm($this->recovery(4000), self::CONFIRMER);

        $this->assertSame(1, DB::table('cb_landlord_account')->count());
    }

    public function test_the_gl_is_left_alone_when_posting_is_off(): void
    {
        config(['accounting.post_invoice_journals' => false]);
        $gl = Mockery::mock(GlJournalPoster::class);
        $gl->shouldReceive('journal')->never();
        $gl->shouldReceive('exists')->never();

        $this->service($gl)->confirm($this->recovery(4000), self::CONFIRMER);

        $this->assertSame(5, (int) Recovery::first()->current_status_id);
    }

    public function test_a_missing_gl_account_refuses_the_confirm_and_rolls_back(): void
    {
        config(['accounting.post_invoice_journals' => true]);
        DB::table('sys_gl_accounts_setup')->update(['tenants_account_id' => null]);
        $gl = Mockery::mock(GlJournalPoster::class);
        $gl->shouldReceive('exists')->andReturn(false);

        try {
            $this->service($gl)->confirm($this->recovery(4000), self::CONFIRMER);
            $this->fail('The confirm went through without a GL account.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('Tenants Account', $e->getMessage());
        }

        $this->assertEquals(10000.0, (float) $this->invoice()->current_balance);
        $this->assertSame(0, DB::table('cb_landlord_account')->count());
    }

    public function test_rejecting_needs_a_reason_and_posts_nothing(): void
    {
        $recovery = $this->recovery(4000);

        try {
            $this->service()->reject($recovery, self::CONFIRMER, '   ');
            $this->fail('A rejection without a reason was accepted.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }
        $this->assertSame(1, (int) $recovery->fresh()->current_status_id);

        $this->service()->reject($recovery, self::CONFIRMER, 'No receipt from the landlord');

        $fresh = $recovery->fresh();
        $this->assertSame(41, (int) $fresh->current_status_id);
        $this->assertSame('No receipt from the landlord', $fresh->rejection_reason);
        $this->assertSame(self::CONFIRMER, (int) $fresh->actioned_by);
        $this->assertEquals(10000.0, (float) $this->invoice()->current_balance);
        $this->assertSame(0, DB::table('sys_pm_tenants_accounts_trans')->count());
        $this->assertSame(0, DB::table('cb_landlord_account')->count());
    }

    public function test_a_rejected_recovery_cannot_be_confirmed_afterwards(): void
    {
        $recovery = $this->recovery(4000);
        $this->service()->reject($recovery, self::CONFIRMER, 'Wrong tenant');

        $this->expectException(ClientAccountException::class);
        $this->service()->confirm($recovery, self::CONFIRMER);
    }

    public function test_a_recovery_of_another_type_is_not_confirmed_here(): void
    {
        DB::table('sys_set_transactions_types')->insert(['id' => 4, 'type_name' => 'Client Expense', 'workflow_status_id' => 1]);

        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('not a paid-owner recovery');

        $this->service()->confirm($this->recovery(4000, ['type_id' => 4]), self::CONFIRMER);
    }

    public function test_recording_checks_the_amount_against_the_balance_and_the_other_waiting_recoveries(): void
    {
        $service = $this->service();
        $this->recovery(7000);
        $data = ['type_id' => 34, 'client_id' => 100, 'property_id' => 300, 'unit_id' => 400, 'tenant_id' => 200, 'offset_invoice_id' => 500, 'amount' => 4000];

        try {
            $service->assertRecordable($data);
            $this->fail('3,000 left after the waiting recovery, 4,000 was accepted.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('3,000.00', $e->getMessage());
        }

        $service->assertRecordable(array_merge($data, ['amount' => 3000]));
        $this->addToAssertionCount(1);
    }

    public function test_recording_refuses_an_invoice_of_another_tenant_or_one_that_is_not_open(): void
    {
        $service = $this->service();
        $data = ['type_id' => 34, 'client_id' => 100, 'property_id' => 300, 'unit_id' => 400, 'tenant_id' => 200, 'offset_invoice_id' => 500, 'amount' => 1000];

        try {
            $service->assertRecordable(array_merge($data, ['tenant_id' => 999]));
            $this->fail('An invoice of another tenant was accepted.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('does not belong', $e->getMessage());
        }

        DB::table('sys_pm_debtors_invoices')->where('id', 500)->update(['current_status_id' => 9, 'current_balance' => 0]);

        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('not open');
        $service->assertRecordable($data);
    }

    public function test_the_landlord_is_the_one_on_the_latest_agreement_then_the_property(): void
    {
        $service = $this->service();
        $this->assertSame(100, $service->landlordOf(300)); // no agreement: the property's client

        DB::table('sys_pm_clients')->insert(['id' => 101, 'names' => 'Other', 'reference_number' => 'LAN-00101']);
        DB::table('sys_pm_service_agreements')->insert([['property_id' => 300, 'client_d' => 100, 'date_start' => '2025-01-01'], ['property_id' => 300, 'client_d' => 101, 'date_start' => '2026-01-01']]);
        $this->assertSame(101, $service->landlordOf(300)); // the latest agreement

        DB::table('sys_pm_service_agreements')->delete();
        DB::table('sys_pm_service_agreements')->insert(['property_id' => 300, 'client_d' => null, 'date_start' => '2026-01-01']);
        $this->assertSame(100, $service->landlordOf(300)); // an agreement that names no client falls back to the property

        DB::table('sys_pm_properties')->where('id', 300)->update(['client_id' => 0]);
        $this->assertNull($service->landlordOf(300));
    }

    public function test_the_client_debit_carries_the_landlord_the_return_uses(): void
    {
        // The property row has no client (as on 198 real properties); the agreement names the landlord
        DB::table('sys_pm_properties')->where('id', 300)->update(['client_id' => 0]);
        DB::table('sys_pm_service_agreements')->insert(['property_id' => 300, 'client_d' => 100, 'date_start' => '2026-01-01']);

        $this->service()->confirm($this->recovery(4000), self::CONFIRMER);

        $this->assertSame(100, (int) DB::table('cb_landlord_account')->value('client_id'));
    }

    public function test_choosing_a_role_replaces_the_old_confirmer_and_is_logged(): void
    {
        DB::table('cb_roles')->insert(['id' => 8, 'name' => 'accountant', 'display_name' => 'Accountant']);
        DB::table('cb_role_user')->insert(['role_id' => 8, 'user_id' => self::OUTSIDER]);
        $service = $this->service();

        $service->setConfirmerRole(8, self::CONFIRMER);

        $this->assertSame(['users' => [], 'roles' => [8]], $service->confirmerSeats());
        $this->assertTrue($service->canConfirm(self::OUTSIDER));
        $this->assertFalse($service->canConfirm(self::CONFIRMER)); // held the old role 7 only
        $this->assertSame('Accountant', $service->confirmerLabel());

        $log = DB::table('workflow_approvers_changes_log')->orderBy('id')->get();
        $this->assertSame(['APPROVER_REMOVED', 'APPROVER_ADDED'], $log->pluck('action')->all());
        $this->assertSame(self::CONFIRMER, (int) $log[1]->changed_by);
        $this->assertSame(1, (int) $log[1]->new_level);
        $this->assertSame(1, (int) $log[1]->new_is_final);
    }

    public function test_a_role_replaces_named_users_too(): void
    {
        DB::table('workflow_approvers')->delete();
        DB::table('workflow_approvers')->insert(['workflow_id' => 1, 'approver_id' => self::OUTSIDER]);

        $this->service()->setConfirmerRole(self::ROLE, self::CONFIRMER);

        $this->assertSame(['users' => [], 'roles' => [self::ROLE]], $this->service()->confirmerSeats());
        $this->assertSame(1, DB::table('workflow_approvers')->count());
    }

    public function test_choosing_the_same_role_again_changes_and_logs_nothing(): void
    {
        $this->service()->setConfirmerRole(self::ROLE, self::CONFIRMER);
        $this->service()->setConfirmerRole(self::ROLE, self::CONFIRMER);

        $this->assertSame(1, DB::table('workflow_approvers')->count());
        $this->assertSame(0, DB::table('workflow_approvers_changes_log')->count());
    }

    public function test_an_unknown_role_or_a_missing_workflow_is_refused_and_leaves_the_seat_alone(): void
    {
        try {
            $this->service()->setConfirmerRole(999, self::CONFIRMER);
            $this->fail('An unknown role was accepted.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }
        $this->assertSame(['users' => [], 'roles' => [self::ROLE]], $this->service()->confirmerSeats());

        DB::table('approval_workflows')->delete();
        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('workflow is missing');
        $this->service()->setConfirmerRole(self::ROLE, self::CONFIRMER);
    }

    public function test_recording_needs_the_tenant_unit_and_invoice(): void
    {
        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('needs the invoice');

        $this->service()->assertRecordable(['type_id' => 34, 'client_id' => 100, 'property_id' => 300, 'unit_id' => 400, 'tenant_id' => 200, 'amount' => 1000]);
    }

    public function test_a_paid_owner_cannot_be_confirmed_without_its_document(): void
    {
        $recovery = $this->recovery(4000, ['proof_path' => null, 'proof_name' => null]);

        try {
            $this->service()->confirm($recovery, self::CONFIRMER);
            $this->fail('A paid owner without its document was confirmed.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('no proof document', $e->getMessage());
        }

        $this->assertEquals(10000.0, (float) $this->invoice()->current_balance);
        $this->assertSame(0, DB::table('cb_landlord_account')->count());
        $this->assertSame(1, (int) $recovery->fresh()->current_status_id);

        // It can still be rejected without a document
        $this->service()->reject($recovery, self::CONFIRMER, 'No document');
        $this->assertSame(41, (int) $recovery->fresh()->current_status_id);
    }

    public function test_a_paid_owner_needs_a_document_but_other_types_do_not(): void
    {
        $service = $this->service();
        $pdf = UploadedFile::fake()->create('ack.pdf', 100, 'application/pdf');

        try {
            $service->assertProofGiven(['type_id' => 34], null, null);
            $this->fail('A paid owner without a document was accepted.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('Attach the proof document', $e->getMessage());
        }

        $service->assertProofGiven(['type_id' => 34], null, $pdf); // a new file
        $service->assertProofGiven(['type_id' => 34], $this->recovery(), null); // one already on the record
        $service->assertProofGiven(['type_id' => 4], null, null); // another type
        $this->addToAssertionCount(3);

        try {
            $service->assertProofGiven(['type_id' => 34], $this->recovery(100, ['ref_number' => 'PO-NODOC', 'proof_path' => null]), null);
            $this->fail('An edit that leaves the recovery with no document was accepted.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('Attach the proof document', $e->getMessage());
        }
    }

    public function test_attaching_stores_the_pdf_privately_and_a_new_one_replaces_the_old(): void
    {
        Storage::fake('local');
        $recovery = $this->recovery(4000, ['proof_path' => null, 'proof_name' => null]);

        $first = $this->service()->attachProof($recovery, UploadedFile::fake()->create('first.pdf', 50, 'application/pdf'), self::CREATOR);

        $this->assertStringStartsWith('recovery_proofs/' . $recovery->id . '/', $first);
        Storage::disk('local')->assertExists($first);
        $fresh = $recovery->fresh();
        $this->assertSame($first, $fresh->proof_path);
        $this->assertSame('first.pdf', $fresh->proof_name);
        $this->assertSame(self::CREATOR, (int) $fresh->proof_uploaded_by);
        $this->assertNotNull($fresh->proof_uploaded_at);

        $second = $this->service()->attachProof($fresh, UploadedFile::fake()->create('second.pdf', 50, 'application/pdf'), self::CREATOR);

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
        $this->assertSame('second.pdf', $recovery->fresh()->proof_name);
    }

    public function test_a_failed_save_leaves_no_orphan_file_behind(): void
    {
        Storage::fake('local');
        // A recovery whose update fails, as when the document columns are missing
        $recovery = Mockery::mock(Recovery::class)->makePartial();
        $recovery->forceFill(['id' => 77, 'current_status_id' => 1]);
        $recovery->shouldReceive('update')->once()->andThrow(new \RuntimeException('column "proof_path" does not exist'));

        try {
            $this->service()->attachProof($recovery, UploadedFile::fake()->create('lost.pdf', 50, 'application/pdf'), self::CREATOR);
            $this->fail('The failure was swallowed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('proof_path', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles()); // the stored file was removed again
    }

    public function test_the_document_is_fixed_once_the_recovery_is_confirmed_or_rejected(): void
    {
        Storage::fake('local');
        $recovery = $this->recovery(4000);
        $this->service()->confirm($recovery, self::CONFIRMER);

        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('still Pending');
        $this->service()->attachProof($recovery->fresh(), UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'), self::CREATOR);
    }

    public function test_who_may_open_the_document(): void
    {
        $recovery = $this->recovery();
        $service = $this->service();

        $this->assertTrue($service->canViewProof($recovery, self::OUTSIDER, true)); // read_recoveries
        $this->assertTrue($service->canViewProof($recovery, self::CREATOR, false)); // the person who recorded it
        $this->assertTrue($service->canViewProof($recovery, self::CONFIRMER, false)); // the confirmer seat
        $this->assertFalse($service->canViewProof($recovery, self::OUTSIDER, false)); // anyone else
    }

    public function test_references_are_a_yearly_sequence_assigned_in_a_transaction(): void
    {
        $service = $this->service();
        $next = fn (?int $year = null) => DB::transaction(fn () => $service->nextReference($year));

        $this->assertSame('REC-2026-00001', $next(2026)); // the first of the year
        $this->recovery(100, ['ref_number' => 'REC-2026-00001']);
        $this->assertSame('REC-2026-00002', $next(2026));

        // Numbers typed by hand before, and other years, do not disturb it
        $this->recovery(100, ['ref_number' => '1794550059234']);
        $this->recovery(100, ['ref_number' => 'PO-1']);
        $this->recovery(100, ['ref_number' => 'REC-2025-00417']);
        $this->assertSame('REC-2026-00002', $next(2026));
        $this->assertSame('REC-2025-00418', $next(2025));
        $this->assertSame('REC-2027-00001', $next(2027)); // a new year starts again

        // The year defaults to the current one (2026 already has its 00001 saved above, in this test's year)
        $this->assertSame($next(now()->year), $next());
        $this->assertStringStartsWith('REC-' . now()->year . '-', $next());
    }

    public function test_the_sequence_passes_five_digits_without_going_wrong(): void
    {
        $service = $this->service();
        $this->recovery(100, ['ref_number' => 'REC-2026-99999']);

        $this->assertSame('REC-2026-100000', DB::transaction(fn () => $service->nextReference(2026)));

        $this->recovery(100, ['ref_number' => 'REC-2026-100000']);
        // 100000 sorts below 99999 as plain text; the sequence must still go on from 100000
        $this->assertSame('REC-2026-100001', DB::transaction(fn () => $service->nextReference(2026)));
    }

    public function test_a_reference_cannot_be_taken_outside_a_transaction(): void
    {
        $this->expectException(\LogicException::class);
        $this->service()->nextReference(2026);
    }

    public function test_the_client_dropdown_searches_all_landlords_not_just_the_first_page(): void
    {
        // 60 landlords, a tenant and a landlord found by reference or mobile
        for ($i = 1; $i <= 60; $i++) {
            DB::table('sys_pm_clients')->insert(['names' => sprintf('Landlord %02d', $i), 'reference_number' => sprintf('LAN-%05d', 1000 + $i), 'mobile_number' => '0700000' . sprintf('%03d', $i), 'type_id' => '1']);
        }
        DB::table('sys_pm_clients')->insert(['names' => 'Landlord Tenant Person', 'reference_number' => 'TEN-1', 'type_id' => '2']);
        $controller = new RecoveryController($this->service());
        $search = fn (array $query) => $controller->searchClients(Request::create('/x', 'GET', $query))->getData(true);

        $first = $search([]);
        $this->assertCount(25, $first['results']);
        $this->assertTrue($first['pagination']['more']);

        $third = $search(['page' => 3]);
        $this->assertCount(10, $third['results']); // 60 landlords: 25 + 25 + 10, the tenant is not one of them
        $this->assertFalse($third['pagination']['more']);

        $byName = $search(['q' => 'landlord 59']);
        $this->assertCount(1, $byName['results']);
        $this->assertSame('LAN-01059 - Landlord 59', $byName['results'][0]['text']);

        $this->assertSame('LAN-01007 - Landlord 07', $search(['q' => 'lan-01007'])['results'][0]['text']); // reference, any case
        $this->assertCount(1, $search(['q' => '0700000042'])['results']);
        $this->assertCount(0, $search(['q' => 'Tenant Person'])['results']); // tenants are not landlords
        $this->assertCount(0, $search(['q' => 'nobody has this name'])['results']);
    }

    public function test_picking_a_unit_gives_its_active_lease_and_that_leases_tenant(): void
    {
        DB::table('sys_pm_clients')->insert([['id' => 210, 'names' => 'Old Tenant', 'reference_number' => 'TEN-210', 'type_id' => '2'], ['id' => 211, 'names' => 'Current Tenant', 'reference_number' => 'TEN-211', 'type_id' => '2']]);
        DB::table('cb_tenant_records')->insert(['id' => 211, 'names' => 'Mirror Name']);
        DB::table('sys_pm_lease_agreements')->insert([
            ['id' => 1, 'unit_id' => 400, 'tenant_id' => 210, 'ref_number' => 'L-1', 'lease_status' => 'Terminated', 'current_status_id' => 2, 'date_start' => '2023-01-01'],
            ['id' => 2, 'unit_id' => 400, 'tenant_id' => 211, 'ref_number' => 'L-2', 'lease_status' => 'Active', 'current_status_id' => 1, 'date_start' => '2025-01-01'],
            ['id' => 3, 'unit_id' => 400, 'tenant_id' => 210, 'ref_number' => 'L-3', 'lease_status' => 'Terminated', 'current_status_id' => 2, 'date_start' => '2026-02-01'], // newer than the active lease
            ['id' => 4, 'unit_id' => 401, 'tenant_id' => 210, 'ref_number' => 'L-4', 'lease_status' => 'Terminated', 'current_status_id' => 2, 'date_start' => null],
        ]);
        $controller = new RecoveryController($this->service());

        $parties = $controller->unitParties(400)->getData(true);

        $this->assertSame(2, $parties['default_lease_id']); // the active lease, though older leases exist
        $this->assertSame([2, 3, 1], array_column($parties['leases'], 'id')); // active first, then newest first
        $this->assertSame([true, false, false], array_column($parties['leases'], 'is_active'));
        $this->assertSame([211, 210], array_column($parties['tenants'], 'id')); // one entry per tenant, current tenant first
        $this->assertSame('Mirror Name', $parties['tenants'][0]['names']); // the tenant record's name, as the rest of the app shows it
        $this->assertSame('TEN-211', $parties['tenants'][0]['reference_number']);

        // A unit with no active lease still offers its latest lease, a unit with no lease offers nothing
        $this->assertSame(4, $controller->unitParties(401)->getData(true)['default_lease_id']);
        $none = $controller->unitParties(999)->getData(true);
        $this->assertNull($none['default_lease_id']);
        $this->assertSame([], $none['leases']);
        $this->assertSame([], $none['tenants']);
    }

    public function test_with_two_active_leases_the_latest_is_the_default(): void
    {
        DB::table('sys_pm_lease_agreements')->insert([
            ['id' => 1, 'unit_id' => 400, 'tenant_id' => 200, 'lease_status' => 'Active', 'current_status_id' => 1, 'date_start' => '2024-06-01'],
            ['id' => 2, 'unit_id' => 400, 'tenant_id' => 200, 'lease_status' => 'Active', 'current_status_id' => 5, 'date_start' => '2025-06-01'],
        ]);

        $this->assertSame(2, (new RecoveryController($this->service()))->unitParties(400)->getData(true)['default_lease_id']);
    }

    public function test_the_invoice_list_can_be_narrowed_to_one_tenant(): void
    {
        DB::table('sys_pm_debtors_invoices')->insert(['id' => 502, 'reference_number' => 'INV502', 'debtor_type_id' => '2', 'total_amount' => 700, 'current_balance' => 700, 'current_status_id' => 1, 'unit_id' => 400, 'tenant_id' => 210]);
        $controller = new RecoveryController($this->service());

        $all = array_column($controller->unitInvoices(Request::create('/x', 'GET'), 400)->getData(true)['invoices'], 'id');
        $this->assertEqualsCanonicalizing([500, 502], $all);

        $narrowed = array_column($controller->unitInvoices(Request::create('/x', 'GET', ['tenant_id' => 210]), 400)->getData(true)['invoices'], 'id');
        $this->assertSame([502], $narrowed);
    }

    public function test_the_form_lists_a_units_open_invoices_and_checks_an_amount(): void
    {
        DB::table('sys_pm_debtors_invoices')->insert(['id' => 501, 'reference_number' => 'INV501', 'debtor_type_id' => '2', 'total_amount' => 500, 'current_balance' => 0, 'current_status_id' => 9, 'unit_id' => 400]);
        $controller = new RecoveryController($this->service());

        $invoices = $controller->unitInvoices(Request::create('/x', 'GET'), 400)->getData(true)['invoices'];
        $this->assertCount(1, $invoices);
        $this->assertSame('INV500', $invoices[0]['invoice_number']);
        $this->assertEquals(10000.0, $invoices[0]['outstanding_amount']);

        $this->assertTrue($controller->validateAmount(Request::create('/x', 'POST', ['amount' => 10000]), 500)->getData(true)['valid']);
        $this->assertFalse($controller->validateAmount(Request::create('/x', 'POST', ['amount' => 10000.01]), 500)->getData(true)['valid']);
    }
}
