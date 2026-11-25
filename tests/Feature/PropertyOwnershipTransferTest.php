<?php

namespace Tests\Feature;

use App\Models\ClientBankAccount;
use App\Models\Deposit;
use App\Models\LandlordAccount;
use App\Models\LeaseAgreement;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyBankDetail;
use App\Models\PropertyOwnershipTransfer;
use App\Models\ReturnsBatchConfigPropertyMapping;
use App\Models\ServiceAgreement;
use App\Models\ServiceAgreementDocument;
use App\Models\SlaInvoiceableItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\PropertyOwnershipResolver;
use App\Services\PropertyTransferAccess;
use App\Services\ServiceAgreementApprovalService;
use Carbon\Carbon;
use Database\Seeders\PropertyOwnershipTransferMenuSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PropertyOwnershipTransferFixtures;
use Tests\TestCase;

class PropertyOwnershipTransferTest extends TestCase
{
    use PropertyOwnershipTransferFixtures;

    public function test_handover_preserves_invoices_and_tenancy_and_opens_only_the_selected_property(): void
    {
        $invoice = DB::table('sys_pm_debtors_invoices')->first();
        $transfer = $this->approved();
        $this->service->complete($transfer, $this->user(1));
        $transfer->refresh();
        $this->assertSame('completed', $transfer->status);
        $this->assertNull($transfer->pending_property_id);
        $this->assertSame(2, (int) Property::find(1)->client_id);
        $this->assertSame(1, (int) Property::find(2)->client_id);
        $this->assertSame((array) $invoice, (array) DB::table('sys_pm_debtors_invoices')->first());
        $lease = LeaseAgreement::find(1);
        $this->assertSame('signed-original.pdf', $lease->signed_pdf_path);
        $this->assertSame(10, (int) $lease->tenant_id);
        $this->assertSame(1, Unit::count());
        $this->assertSame(2, (int) Unit::first()->client_id);
        $this->assertSame('2026-11-30', ServiceAgreement::find(1)->date_end);
        $this->assertSame(5, (int) ServiceAgreement::find($transfer->new_sla_id)->current_status_id);
        $this->assertSame('12', (string) ServiceAgreement::find($transfer->new_sla_id)->commission_percentage);
        $this->assertSame(2, SlaInvoiceableItem::count());
        $this->assertSame('Company', Deposit::find(1)->held_by);
        $this->assertSame(2, (int) Deposit::find(1)->client_id);
        $this->assertSame(2, (int) PropertyBankDetail::first()->client_id);
        $this->assertSame('1234002', ClientBankAccount::find(Property::find(1)->bank_account_id)->account_number);
        $this->assertSame(1, ReturnsBatchConfigPropertyMapping::count());
        $this->assertSame(2, LandlordAccount::where('entry_type_code', 'OWN')->count());
        $this->assertSame(0.0, (float) LandlordAccount::sum('debit_amount'));
        $this->assertSame(0.0, (float) LandlordAccount::sum('credit_amount'));
        $this->assertArrayNotHasKey('client_password', $transfer->snapshot['rows']['client']);
        $this->assertSame(4, $transfer->events()->count());
        $this->service->complete($transfer, $this->user(1));
        $this->assertSame(2, ServiceAgreement::count());
        $this->assertSame(1, DB::table('property_transfer_invoice_assignments')->count());
        $this->assertSame(4, $transfer->events()->count());
    }

    public function test_ownership_and_arrears_rights_resolve_by_period_without_rewriting_history(): void
    {
        $transfer = $this->approved();
        $this->service->complete($transfer, $this->user(1));
        $resolver = app(PropertyOwnershipResolver::class);
        $this->assertSame(1, (int) $resolver->agreement(1, '2026-11-01')->client_d);
        $this->assertSame(2, (int) $resolver->agreement(1, '2026-12-01')->client_d);
        $this->assertSame(1, (int) LeaseAgreement::find(1)->resolvedServiceAgreement('2026-11-01')->id);
        $invoice = DB::table('sys_pm_debtors_invoices')->first();
        $this->assertSame(1, $resolver->invoiceClient($invoice, '2026-11-30'));
        $this->assertSame(2, $resolver->invoiceClient($invoice, '2026-12-01'));
        $this->assertNull($resolver->agreement(2, '2026-12-01'));
    }

    public function test_retained_arrears_are_not_assigned_to_the_new_owner(): void
    {
        $transfer = $this->approved(['arrears_policy' => 'retain']);
        $this->service->complete($transfer, $this->user(1));
        $this->assertSame(0, DB::table('property_transfer_invoice_assignments')->count());
        $this->assertSame(1, app(PropertyOwnershipResolver::class)->invoiceClient(DB::table('sys_pm_debtors_invoices')->first()));
    }

    public function test_mid_month_backdated_same_owner_and_foreign_bank_requests_are_rejected(): void
    {
        $this->rejected(fn () => $this->transfer(['effective_date' => '2026-12-15']), 'first day');
        $this->rejected(fn () => $this->transfer(['effective_date' => '2026-11-01']), 'first day');
        $this->rejected(fn () => $this->transfer(['to_client_id' => 1, 'bank_detail_id' => 1]), 'different landlord');
        try {
            $this->transfer(['bank_detail_id' => 1]);
            $this->fail('Foreign bank accepted.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, PropertyOwnershipTransfer::count());
        }
    }

    public function test_duplicate_pending_transfers_are_blocked_and_cancel_releases_the_property(): void
    {
        $transfer = $this->transfer();
        $this->rejected(fn () => $this->transfer(), 'unfinished transfer');
        $this->service->reset($transfer, $this->user(1), 'Cancelled on the owner’s instructions.', true);
        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertNotSame($transfer->id, $this->transfer()->id);
    }

    public function test_review_and_approval_stages_are_still_required(): void
    {
        $transfer = $this->transfer();
        $this->rejected(fn () => $this->service->complete($transfer, $this->user(1)), 'Approve');
        $this->rejected(fn () => $this->service->approve($transfer, $this->user(1), 'Attempt without review'), 'Finance');
        $this->assertSame('draft', $transfer->fresh()->status);
    }

    public function test_director_can_create_reconcile_approve_and_complete_from_the_same_account(): void
    {
        $this->withoutExceptionHandling();
        $this->post(route('property-transfers.store'), [
            'property_id' => 1, 'to_client_id' => 2, 'bank_detail_id' => 2, 'effective_date' => '2026-12-01',
            'arrears_policy' => 'transfer', 'commission_percentage' => 12, 'duration_years' => 1,
            'reason' => 'Director records the ownership handover.',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $transfer = PropertyOwnershipTransfer::firstOrFail();

        $this->post(route('property-transfers.decide', $transfer), [
            'action' => 'review', 'notes' => 'Director reconciled balances, deposits and payout instructions.', 'confirmed' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('reviewed', $transfer->fresh()->status);
        $this->assertSame(1, (int) $transfer->fresh()->reviewed_by);

        $this->post(route('property-transfers.decide', $transfer), [
            'action' => 'approve', 'notes' => 'Director approves the reconciled transfer and SLA terms.',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $transfer->fresh()->status);
        $this->assertSame(1, (int) $transfer->fresh()->approved_by);

        $this->post(route('property-transfers.decide', $transfer), ['action' => 'complete'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('completed', $transfer->fresh()->status);
        $this->assertSame(2, (int) Property::find(1)->client_id);
        $this->assertSame(['created', 'finance_reviewed_by_director', 'approved', 'completed'], $transfer->events()->orderBy('id')->pluck('action')->all());
        $this->assertSame([1], $transfer->events()->distinct()->pluck('actor_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_accountant_cannot_review_their_own_request_or_approve_their_own_review(): void
    {
        $transfer = $this->transfer();
        $transfer->update(['created_by' => 2]);
        $this->rejected(fn () => $this->service->review($transfer, $this->user(2), 'Trying to review own handover'), 'different Finance');
        $transfer->update(['created_by' => 1]);
        $this->service->review($transfer, $this->user(2), 'Accountant reconciled the director request.');
        $permission = DB::table('cb_permissions')->where('name', 'approve_property_transfers')->value('id');
        DB::table('cb_permission_role')->insert(['role_id' => 2, 'permission_id' => $permission]);
        $this->rejected(fn () => $this->service->approve($transfer->fresh(), $this->user(2), 'Trying to approve own review'), 'cannot also give final approval');
        $this->assertSame('reviewed', $transfer->fresh()->status);
    }

    public function test_director_reconciliation_still_requires_confirmation_and_clear_balances(): void
    {
        $transfer = $this->transfer();
        $this->post(route('property-transfers.decide', $transfer), [
            'action' => 'review', 'notes' => 'Director review without checking the declaration.',
        ])->assertSessionHasErrors('confirmed');
        $this->assertSame('draft', $transfer->fresh()->status);
        DB::table('cb_landlord_account')->insert(['property_id' => 1, 'client_id' => 1, 'entry_type_code' => '0023', 'entry_description' => 'Registration', 'debit_amount' => 500, 'is_recovered' => 0]);
        $this->rejected(fn () => $this->service->review($transfer, $this->user(1), 'Director review of unsettled account'), 'Settle');
        $this->assertSame('draft', $transfer->fresh()->status);
        $this->assertSame(1, (int) Property::find(1)->client_id);
    }

    public function test_director_exception_requires_the_current_role_and_review_permission(): void
    {
        $transfer = $this->transfer();
        $permission = DB::table('cb_permissions')->where('name', 'review_property_transfers')->value('id');
        DB::table('cb_permission_role')->where('role_id', 1)->where('permission_id', $permission)->delete();
        $this->assertFalse(PropertyTransferAccess::allows($this->user(1), 'review'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('property-transfers.decide', $transfer), [
                'action' => 'review', 'notes' => 'Director no longer has permission to review.', 'confirmed' => '1',
            ]);
            $this->fail('Review permission was revoked but the request was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        // The seeder safely updates existing Director grants as well as fresh installs.
        (new PropertyOwnershipTransferMenuSeeder)->run();
        $this->assertTrue(PropertyTransferAccess::allows($this->user(1), 'review'));
        $this->service->review($transfer, $this->user(1), 'Director reconciles before role reassignment.');
        DB::table('cb_roles')->where('id', 1)->update(['name' => 'Manager', 'display_name' => 'Manager']);
        $this->rejected(fn () => $this->service->approve($transfer->fresh(), $this->user(1), 'Previous director attempts to approve'), 'cannot also give final approval');
    }

    public function test_changed_financial_data_invalidates_approval_and_can_be_reviewed_again(): void
    {
        $transfer = $this->approved();
        DB::table('sys_pm_debtors_invoices')->where('id', 1)->update(['current_balance' => 600]);
        $this->rejected(fn () => $this->service->complete($transfer, $this->user(1)), 'changed after review');
        $this->assertSame(1, (int) Property::find(1)->client_id);
        $this->service->reset($transfer, $this->user(1), 'Payment received since previous reconciliation.');
        $this->service->review($transfer->fresh(), $this->user(2), 'Updated allocation and deposits reconciled.');
        $this->service->approve($transfer->fresh(), $this->user(1), 'Updated handover approved.');
        $this->service->complete($transfer->fresh(), $this->user(1));
        $this->assertSame('600', (string) DB::table('property_transfer_invoice_assignments')->value('balance_at_transfer'));
    }

    public function test_outstanding_landlord_balance_advances_and_returns_block_finance_review(): void
    {
        $transfer = $this->transfer();
        DB::table('cb_landlord_account')->insert(['property_id' => 1, 'client_id' => 1, 'entry_type_code' => '0023', 'entry_description' => 'Registration', 'debit_amount' => 500, 'is_recovered' => 0]);
        DB::table('sys_pm_clients_advances_requisitions')->insert(['property_id' => 1, 'client_id' => 1, 'current_balance' => 1000, 'current_status_id' => 6]);
        DB::table('cb_returns_summary')->insert(['property_code' => '1055E', 'paid_status' => 0]);
        $snapshot = $this->service->snapshot($transfer);
        $this->assertCount(4, $snapshot['summary']['blockers']);
        $this->rejected(fn () => $this->service->review($transfer, $this->user(2), 'Unsettled position'), 'Settle');
        $this->assertSame(1, (int) Property::find(1)->client_id);
    }

    public function test_future_invoice_and_future_execution_are_blocked(): void
    {
        $transfer = $this->approved(['effective_date' => '2027-01-01']);
        $this->rejected(fn () => $this->service->complete($transfer, $this->user(1)), 'effective date');
        DB::table('sys_pm_debtors_invoices')->where('id', 1)->update(['returns_period' => '2027-01-01']);
        $this->assertStringContainsString('Invoices already exist', implode(' ', $this->service->snapshot($transfer)['summary']['blockers']));
    }

    public function test_failed_sla_approval_rolls_back_every_part_of_the_transfer(): void
    {
        $transfer = $this->approved();
        $this->mock(ServiceAgreementApprovalService::class, function ($mock) {
            $mock->shouldReceive('approve')->andReturn(['done' => [], 'skipped' => []]);
        });
        $this->rejected(fn () => $this->service->complete($transfer, $this->user(1)), 'Nothing was transferred');
        $this->assertSame(1, (int) Property::find(1)->client_id);
        $this->assertSame('2027-01-01', ServiceAgreement::find(1)->date_end);
        $this->assertSame(1, ServiceAgreement::count());
        $this->assertSame(0, ClientBankAccount::count());
        $this->assertSame(0, PropertyBankDetail::count());
        $this->assertSame('approved', $transfer->fresh()->status);
    }

    public function test_revoking_an_action_permission_blocks_direct_service_calls(): void
    {
        $transfer = $this->approved();
        $permission = DB::table('cb_permissions')->where('name', 'execute_property_transfers')->value('id');
        DB::table('cb_permission_role')->where('permission_id', $permission)->delete();
        try {
            $this->service->complete($transfer, $this->user(1));
            $this->fail('Revoked permission accepted.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(1, (int) Property::find(1)->client_id);
    }

    public function test_menu_seeder_is_repeatable_and_does_not_grant_field_officers_approval(): void
    {
        (new PropertyOwnershipTransferMenuSeeder)->run();
        $this->assertSame(1, DB::table('cb_sub_modules')->count());
        $this->assertSame(5, DB::table('cb_permissions')->count());
        $this->assertTrue(PropertyTransferAccess::allows($this->user(1), 'approve'));
        $this->assertFalse(PropertyTransferAccess::allows($this->user(2), 'approve'));
        $this->assertFalse(PropertyTransferAccess::allows($this->user(3), 'view'));
    }

    public function test_a_later_transfer_does_not_take_arrears_retained_by_an_earlier_owner(): void
    {
        $first = $this->approved(['arrears_policy' => 'retain']);
        $this->service->complete($first, $this->user(1));
        $this->travelTo(Carbon::parse('2027-01-01 09:00:00'));
        $second = $this->approved(['to_client_id' => 3, 'bank_detail_id' => 3, 'effective_date' => '2027-01-01']);
        $this->service->complete($second, $this->user(1));
        $this->assertSame(3, (int) Property::find(1)->client_id);
        $this->assertSame(0, DB::table('property_transfer_invoice_assignments')->count());
        $this->assertSame(1, app(PropertyOwnershipResolver::class)->invoiceClient(DB::table('sys_pm_debtors_invoices')->first()));
        $this->assertSame(2, PropertyOwnershipTransfer::where('status', 'completed')->count());
    }

    public function test_a_legacy_sla_without_a_client_id_retains_its_recorded_outgoing_owner(): void
    {
        DB::table('sys_pm_service_agreements')->where('id', 1)->update(['client_d' => null]);
        $transfer = $this->approved();
        $this->service->complete($transfer, $this->user(1));
        $this->assertSame(1, (int) app(PropertyOwnershipResolver::class)->agreement(1, '2026-11-01')->client_d);
        $this->assertNull(ServiceAgreement::find(1)->client_d);
    }

    public function test_http_approval_does_not_require_the_finance_only_checkbox(): void
    {
        $transfer = $this->transfer();
        $this->service->review($transfer, $this->user(2), 'Figures and signed handover reconciled.');
        $this->withoutExceptionHandling()->actingAs($this->user(1));
        $this->post(route('property-transfers.decide', $transfer), ['action' => 'approve', 'notes' => 'Director approves the reconciled handover.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $transfer->fresh()->status);
    }

    public function test_a_request_can_be_created_without_supporting_documents(): void
    {
        Storage::fake('local');
        $this->withoutExceptionHandling();
        $this->post(route('property-transfers.store'), [
            'property_id' => 1, 'to_client_id' => 2, 'bank_detail_id' => 2, 'effective_date' => '2026-12-01',
            'arrears_policy' => 'transfer', 'commission_percentage' => 12, 'duration_years' => 1,
            'reason' => 'Ownership change with documents to follow.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $transfer = PropertyOwnershipTransfer::firstOrFail();
        $this->assertSame([], $transfer->documents);
        $this->assertSame('draft', $transfer->status);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(1, (int) Property::find(1)->client_id);
    }

    public function test_optional_supporting_documents_are_still_stored_privately_when_provided(): void
    {
        Storage::fake('local');
        $this->withoutExceptionHandling();
        $this->post(route('property-transfers.store'), [
            'property_id' => 1, 'to_client_id' => 2, 'bank_detail_id' => 2, 'effective_date' => '2026-12-01',
            'arrears_policy' => 'transfer', 'commission_percentage' => 12, 'duration_years' => 1,
            'reason' => 'Ownership change with supporting authority.',
            'handover_documents' => [UploadedFile::fake()->create('authority.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $document = PropertyOwnershipTransfer::firstOrFail()->documents[0];
        $this->assertSame('authority.pdf', $document['name']);
        Storage::disk('local')->assertExists($document['path']);
    }

    public function test_optional_documents_must_still_have_an_allowed_type(): void
    {
        Storage::fake('local');
        $this->post(route('property-transfers.store'), [
            'property_id' => 1, 'to_client_id' => 2, 'bank_detail_id' => 2, 'effective_date' => '2026-12-01',
            'arrears_policy' => 'transfer', 'commission_percentage' => 12, 'duration_years' => 1,
            'reason' => 'Ownership change with supporting authority.',
            'handover_documents' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('handover_documents.0');

        $this->assertSame(0, PropertyOwnershipTransfer::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_pending_property_payment_vouchers_prevent_closing_the_old_account(): void
    {
        $transfer = $this->transfer();
        DB::table('cb_payment_vouchers_headers_new')->insert(['voucher_number' => 'PV-TEST', 'voucher_status' => 'Pending']);
        DB::table('cb_payment_vouchers_lines_new')->insert(['voucher_number' => 'PV-TEST', 'property_code' => '1055E', 'amount' => 300]);
        $this->rejected(fn () => $this->service->review($transfer, $this->user(2), 'Pending supplier voucher'), 'pending payment vouchers');
        $this->assertSame(1, (int) Property::find(1)->client_id);
    }

    public function test_due_reminder_appears_in_the_bell_once_without_completing_the_transfer(): void
    {
        $transfer = $this->approved();
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $notification = Notification::firstOrFail();
        $this->assertSame(1, (int) $notification->notifiable_id);
        $this->assertSame(User::class, $notification->notifiable_type);
        $this->assertSame($transfer->id, $notification->data['transfer_id']);
        $this->assertSame('2026-12-01', $notification->data['effective_date']);
        $this->assertSame(route('property-transfers.show', $transfer, false), $notification->action_url);
        $this->assertStringContainsString('Robert Rentals', $notification->message);
        $this->getJson(route('notifications.feed', ['after_id' => 0]))
            ->assertOk()->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Ownership transfer due today')
            ->assertJsonPath('notifications.0.action_url', $notification->action_url);

        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $notification->markAsRead();
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(1, Notification::count());
        $this->assertSame('approved', $transfer->fresh()->status);
        $this->assertSame(1, (int) Property::find(1)->client_id);
        $this->assertSame(1, ServiceAgreement::count());
    }

    public function test_reminder_waits_for_the_nairobi_effective_date_and_catches_late_approval(): void
    {
        $transfer = $this->transfer();
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(0, Notification::count());
        $this->service->review($transfer, $this->user(1), 'Director completed the reconciliation.');
        $this->service->approve($transfer->fresh(), $this->user(1), 'Director approves the handover.');

        // Even if another environment uses UTC, the date is always evaluated in Nairobi.
        config(['app.timezone' => 'UTC']);
        $this->travelTo(Carbon::parse('2026-11-30 20:59:00', 'UTC'));
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(0, Notification::count());
        $this->travelTo(Carbon::parse('2026-11-30 21:01:00', 'UTC'));
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(1, Notification::count());
    }

    public function test_reminders_are_not_sent_to_blocked_or_unauthorized_directors(): void
    {
        $this->approved();
        DB::table('josya_users')->where('id', 1)->update(['block' => true]);
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(0, Notification::count());
        DB::table('josya_users')->where('id', 1)->update(['block' => false]);
        $execute = DB::table('cb_permissions')->where('name', 'execute_property_transfers')->value('id');
        DB::table('cb_permission_role')->where('role_id', 1)->where('permission_id', $execute)->delete();
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(0, Notification::count());
        (new PropertyOwnershipTransferMenuSeeder)->run();
        DB::table('cb_sub_module_permissions')->where('role_id', 1)->delete();
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(0, Notification::count());
    }

    public function test_reminders_respect_property_scope_and_restore_the_original_user(): void
    {
        $this->approved();
        // This Director has action grants, but no property assignment or global scope.
        DB::table('cb_roles')->where('id', 1)->update(['is_global' => false]);
        $original = auth()->user();
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(0, Notification::count());
        $this->assertSame($original, auth()->user());
        DB::table('sys_user_staff_links')->insert(['user_id' => 1, 'staff_id' => 50]);
        DB::table('sys_pm_properties')->where('id', 1)->update(['field_officer_id' => 50]);
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(1, Notification::count());
        $this->assertSame($original, auth()->user());
    }

    public function test_reminder_preview_does_not_save_notifications(): void
    {
        $transfer = $this->approved();
        $this->artisan('property-transfers:remind-due --dry-run')
            ->expectsOutputToContain('would create 1 in-app reminder(s)')->assertSuccessful();
        $this->assertSame(0, Notification::count());
        $this->assertSame('approved', $transfer->fresh()->status);
    }

    public function test_reminders_are_cleared_after_completion_and_no_new_reminder_is_sent(): void
    {
        $transfer = $this->approved();
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->service->complete($transfer, $this->user(1));
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(1, Notification::count());
        $this->assertSame(0, Notification::unread()->count());
    }

    public function test_reminders_stop_when_review_is_reset_or_the_date_is_missed(): void
    {
        $transfer = $this->approved();
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->service->reset($transfer, $this->user(1), 'Figures changed and need a fresh review.');
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(0, Notification::unread()->count());
        $this->service->review($transfer->fresh(), $this->user(1), 'Updated handover reconciled by Director.');
        $this->service->approve($transfer->fresh(), $this->user(1), 'Updated handover approved by Director.');
        $this->travelTo(Carbon::parse('2026-12-02 09:00:00'));
        $this->artisan('property-transfers:remind-due')->assertSuccessful();
        $this->assertSame(1, Notification::count());
        $this->assertSame(0, Notification::unread()->count());
    }

    private function renderWithoutNavigation(): void
    {
        // This fixture isolates property transfers from menu configuration and staff HR tables.
        view()->getDispatcher()->forget('composing: layouts.main');
        view()->share(['menus' => [], 'superModuleMenus' => [], 'forcePasswordChange' => false]);
        $this->withoutExceptionHandling();
    }

    public function test_review_page_guides_the_director_and_links_to_a_readable_outgoing_agreement(): void
    {
        $this->renderWithoutNavigation();
        $transfer = $this->transfer();
        $this->get(route('property-transfers.show', $transfer))->assertOk()
            ->assertSee('Review and reconcile')->assertSee('Record reconciliation (Director)')
            ->assertSee('Creating or approving this request does not change the landlord yet.')
            ->assertSee(route('property-transfers.agreement', [$transfer, 'outgoing']), false)
            ->assertSee('Created at completion')->assertSee('Approval history');
    }

    public function test_outgoing_agreement_is_html_and_preserves_the_old_landlord_after_completion(): void
    {
        $this->renderWithoutNavigation();
        $transfer = $this->approved();
        $this->service->complete($transfer, $this->user(1));
        $response = $this->get(route('property-transfers.agreement', [$transfer, 'outgoing']));
        $response->assertOk()->assertViewIs('property.ownership-transfers.agreement')
            ->assertSee('Service level agreement')->assertSee('Outgoing')
            ->assertSee('Service Charge')->assertSee('0037')->assertSee('Monthly')->assertSee('Advance payable')
            ->assertSee('10.00%')->assertSee('30 Nov 2026')->assertSee('No SLA documents attached.');
        $this->assertSame(1, (int) $response->viewData('landlord')->id);
        $incoming = $this->get(route('property-transfers.agreement', [$transfer, 'incoming']));
        $incoming->assertOk()->assertSee('12.00%');
        $this->assertSame(2, (int) $incoming->viewData('landlord')->id);
        $this->assertSame(2, (int) $incoming->viewData('agreement')->client_d);
    }

    public function test_transfer_sla_documents_are_downloadable_only_for_the_linked_agreement(): void
    {
        Storage::fake('public');
        $transfer = $this->transfer();
        Storage::disk('public')->put('sla_documents/previous.pdf', 'test document');
        $document = ServiceAgreementDocument::create(['parent_id' => 1, 'sla_documents' => 'sla_documents/previous.pdf']);
        $this->withoutExceptionHandling();
        $this->get(route('property-transfers.agreement-document', [$transfer, 'outgoing', $document->id]))
            ->assertOk()->assertDownload('previous.pdf');

        $foreign = ServiceAgreementDocument::create(['parent_id' => 999, 'sla_documents' => 'sla_documents/previous.pdf']);
        try {
            $this->get(route('property-transfers.agreement-document', [$transfer, 'outgoing', $foreign->id]));
            $this->fail('A document from an unrelated SLA was accessible.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_sla_view_requires_transfer_access_and_an_existing_incoming_agreement(): void
    {
        $transfer = $this->transfer();
        $this->withoutExceptionHandling();
        try {
            $this->get(route('property-transfers.agreement', [$transfer, 'incoming']));
            $this->fail('An incoming agreement was shown before completion.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->actingAs($this->user(3));
        try {
            $this->get(route('property-transfers.agreement', [$transfer, 'outgoing']));
            $this->fail('A field officer without transfer access could read the agreement.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_review_page_explains_each_remaining_step_without_changing_the_workflow(): void
    {
        $this->renderWithoutNavigation();
        $transfer = $this->transfer();
        $this->service->review($transfer, $this->user(1), 'Director reconciled all balances and deposit custody.');
        $this->get(route('property-transfers.show', $transfer))->assertOk()
            ->assertSee('Approve the handover')->assertSee('Approval notes')
            ->assertSee('Approve handover &amp; SLA terms', false)->assertDontSee('id="confirmed"', false);
        $this->service->approve($transfer->fresh(), $this->user(1), 'Director approves the handover terms.');
        $this->travelTo(Carbon::parse('2026-11-30 09:00:00'));
        $this->get(route('property-transfers.show', $transfer))->assertOk()
            ->assertSee('Scheduled for 01 Dec 2026')
            ->assertSee('value="complete" class="btn btn-primary" disabled', false);
        $this->travelTo(Carbon::parse('2026-12-02 09:00:00'));
        $this->get(route('property-transfers.show', $transfer))->assertOk()
            ->assertSee('Effective date has passed')->assertDontSee('value="complete"', false);
        $this->assertSame('approved', $transfer->fresh()->status);
        $this->assertSame(1, (int) Property::find(1)->client_id);
    }
}
