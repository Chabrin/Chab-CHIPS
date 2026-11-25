<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientBankAccount;
use App\Models\ClientBankDetail;
use App\Models\LandlordAccount;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\ReturnsBatchConfigPropertyMapping;
use App\Models\ReturnsBatchConfiguration;
use App\Models\ServiceAgreement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PropertyOwnershipTransferService
{
    public function access(User $user, string $action): void
    {
        abort_unless(PropertyTransferAccess::allows($user, 'view') && PropertyTransferAccess::allows($user, $action), 403);
    }

    public function visible(PropertyOwnershipTransfer $transfer, User $user): Property
    {
        $this->access($user, 'view');

        return Property::findOrFail($transfer->property_id);
    }

    public function create(array $data, User $user): PropertyOwnershipTransfer
    {
        $this->access($user, 'create');

        return DB::transaction(function () use ($data, $user) {
            $property = Property::lockForUpdate()->findOrFail($data['property_id']);
            $date = Carbon::parse($data['effective_date'])->startOfDay();
            $this->check($date->day === 1 && $date->gte(today()), 'effective_date', 'Use the first day of a month, today or later. Mid-month ownership splits are not supported by monthly returns.');
            $this->check(! PropertyOwnershipTransfer::where('pending_property_id', $property->id)->exists(), 'property_id', 'This property already has an unfinished transfer.');
            $last = PropertyOwnershipTransfer::where('property_id', $property->id)->where('status', 'completed')->max('effective_date');
            $this->check(! $last || $date->gt(Carbon::parse($last)), 'effective_date', 'The effective date must follow the previous transfer.');
            $client = Client::findOrFail($data['to_client_id']);
            $this->check((int) $property->client_id !== (int) $client->id, 'to_client_id', 'Select a different landlord.');
            $this->check(str_starts_with((string) $client->reference_number, 'LAN') && (int) $client->current_status_id === 5, 'to_client_id', 'The incoming landlord must be an approved landlord client.');
            $this->check((bool) ($client->mobile_number || $client->email_address), 'to_client_id', 'Add a phone number or email for the incoming landlord.');
            $bank = ClientBankDetail::where('client_id', $client->id)->findOrFail($data['bank_detail_id']);
            $this->check((bool) $bank->account_number && (bool) $bank->account_name, 'bank_detail_id', 'Complete the incoming landlord’s bank account details first.');
            $sla = ServiceAgreement::where('property_id', $property->id)->where(fn ($q) => $q->where('client_d', $property->client_id)->orWhereNull('client_d'))
                ->approved()->where(fn ($q) => $q->whereNull('date_start')->orWhereDate('date_start', '<=', today()))
                ->orderByRaw('date_start DESC NULLS LAST')->orderByDesc('id')->first();
            $this->check((bool) $sla, 'property_id', 'Approve the current landlord’s SLA before starting an ownership transfer.');
            $this->check(! $sla->date_end || Carbon::parse($sla->date_end)->gte($date->copy()->subDay()), 'effective_date', 'The current SLA ends before the proposed handover. Resolve the SLA gap first.');
            $terms = $sla->only($sla->getFillable());
            foreach (['date_time', 'client_d', 'date_start', 'date_end', 'reference_number', 'created_by_id', 'current_status_id', 'sla_documents'] as $field) {
                unset($terms[$field]);
            }
            $terms['commission_percentage'] = $data['commission_percentage'];
            $terms['years'] = $data['duration_years'];
            $terms['charge_registration_fee'] = false;
            $transfer = PropertyOwnershipTransfer::create([
                'property_id' => $property->id, 'pending_property_id' => $property->id,
                'from_client_id' => $property->client_id, 'to_client_id' => $client->id,
                'old_sla_id' => $sla->id, 'bank_detail_id' => $bank->id, 'effective_date' => $date,
                'arrears_policy' => $data['arrears_policy'], 'reason' => $data['reason'], 'terms' => $terms,
                'documents' => $data['documents'], 'created_by' => $user->id, 'status' => 'draft',
            ]);
            $this->record($transfer, $user, 'created', $data['reason']);

            return $transfer;
        });
    }

    /** Includes original rows, not just totals: a changed allocation or payee invalidates approval. */
    public function snapshot(PropertyOwnershipTransfer $transfer): array
    {
        $property = Property::findOrFail($transfer->property_id);
        $tables = [
            'ledger' => 'cb_landlord_account', 'invoices' => 'sys_pm_debtors_invoices', 'deposits' => 'sys_pm_deposits',
            'leases' => 'sys_pm_lease_agreements', 'units' => 'sys_pm_units', 'payments' => 'sys_pm_tenants_payments',
            'advances' => 'sys_pm_clients_advances_requisitions', 'remittances' => 'sys_fin_reccurent_client_remittances',
            'bank_mappings' => 'property_bank_details',
        ];
        $rows = [];
        foreach ($tables as $key => $table) {
            // Required financial tables must exist; a missing table cannot mean a zero balance.
            $query = DB::table($table)->where('property_id', $property->id);
            if ($key === 'ledger') {
                $query->orWhere(fn ($q) => $q->whereNull('property_id')->where('property_code', $property->reference_number));
            }
            $rows[$key] = $query->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        $rows['tenant_ledger'] = DB::table('sys_pm_tenants_accounts_trans')->where('property_id', $property->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $rows['tenant_links'] = DB::table('sys_pm_clients')->whereIn('id', array_column($rows['leases'], 'tenant_id'))
            ->orderBy('id')->get(['id', 'property_id', 'client_id', 'sla_id'])->map(fn ($r) => (array) $r)->all();
        $rows['payment_allocations'] = DB::table('sys_pm_tenants_payments_invoices')->whereIn('payment_id', array_column($rows['payments'], 'id'))->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $rows['deposit_allocations'] = DB::table('sys_pm_deposit_allocations')->whereIn('deposit_id', array_column($rows['deposits'], 'id'))->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $rows['payout_mappings'] = DB::table('sys_eports_returns_batches_configurations_298_repeat_repeat_prs')->where('prs', $property->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $rows['returns'] = DB::table('cb_returns_summary')->where('property_code', $property->reference_number)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $rows['voucher_lines'] = [];
        $rows['voucher_headers'] = [];
        if (Schema::hasTable('cb_payment_vouchers_lines_new') && Schema::hasTable('cb_payment_vouchers_headers_new')) {
            $rows['voucher_lines'] = DB::table('cb_payment_vouchers_lines_new')->where('property_code', $property->reference_number)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
            $rows['voucher_headers'] = DB::table('cb_payment_vouchers_headers_new')->whereIn('voucher_number', array_column($rows['voucher_lines'], 'voucher_number'))->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }
        $rows['property'] = $property->getAttributes();
        $rows['old_sla'] = ServiceAgreement::findOrFail($transfer->old_sla_id)->getAttributes();
        $rows['client'] = Client::withoutGlobalScopes()->findOrFail($transfer->to_client_id)->only(['id', 'names', 'reference_number', 'current_status_id', 'mobile_number', 'email_address']);
        $rows['outgoing_client'] = Client::withoutGlobalScopes()->findOrFail($transfer->from_client_id)->only(['id', 'names', 'reference_number']);
        $rows['bank'] = ClientBankDetail::where('client_id', $transfer->to_client_id)->findOrFail($transfer->bank_detail_id)->getAttributes();
        $rows['sla_children'] = $this->slaChildren($transfer->old_sla_id);
        $ledger = array_filter($rows['ledger'], fn ($r) => (int) $r['client_id'] === (int) $transfer->from_client_id);
        $balance = 0;
        foreach ($ledger as $row) {
            if (app(ClientLedgerService::class)->counts($row['entry_type_code'] ?? null, $row['entry_description'] ?? null)) {
                $balance += abs($this->cents($row['credit_amount'] ?? 0)) - abs($this->cents($row['debit_amount'] ?? 0));
            }
        }
        $openInvoices = array_values(array_filter($rows['invoices'], fn ($r) => in_array((int) $r['current_status_id'], [1, 8], true) && $this->cents($r['current_balance'] ?? $r['total_amount']) > 0));
        $invoiceOwners = [];
        foreach ($openInvoices as $invoice) {
            $invoiceOwners[$invoice['id']] = app(PropertyOwnershipResolver::class)->invoiceClient((object) $invoice)
                ?? ((int) ($invoice['client_id'] ?? 0) ?: (int) $transfer->from_client_id);
        }
        $blockers = [];
        if ((int) $rows['client']['current_status_id'] !== 5 || ! str_starts_with((string) $rows['client']['reference_number'], 'LAN')) {
            $blockers[] = 'The incoming landlord must remain an approved landlord client.';
        }
        if ((int) $rows['old_sla']['current_status_id'] !== ServiceAgreement::STATUS_APPROVED) {
            $blockers[] = 'The outgoing SLA must remain approved until handover.';
        }
        if ((int) $property->client_id !== (int) $transfer->from_client_id) {
            $blockers[] = 'The property’s landlord has changed. Cancel this request and start a fresh transfer.';
        }
        if ($balance !== 0) {
            $blockers[] = 'Settle the outgoing landlord’s property ledger first. Its balance is '.number_format($balance / 100, 2).'. Historical balances are not written off or reassigned automatically.';
        }
        if (array_filter($ledger, fn ($r) => $this->cents($r['debit_amount'] ?? 0) > 0 && (int) ($r['is_recovered'] ?? 0) !== 1 && app(ClientLedgerService::class)->counts($r['entry_type_code'] ?? null, $r['entry_description'] ?? null))) {
            $blockers[] = 'Reconcile unrecovered landlord debits before closing this property’s account.';
        }
        if (array_filter($rows['advances'], fn ($r) => $this->cents($r['current_balance'] ?? 0) > 0 || in_array((int) $r['current_status_id'], [1, 5], true))) {
            $blockers[] = 'Resolve outstanding or pending landlord advances separately before transfer.';
        }
        if (array_filter($rows['returns'], fn ($r) => (int) ($r['paid_status'] ?? 0) !== 1)) {
            $blockers[] = 'Complete or cancel every pending/unpaid return and its payment voucher before transfer.';
        }
        if (array_filter($rows['voucher_headers'], fn ($r) => ! in_array($r['voucher_status'] ?? 'Pending', ['Approved', 'Rejected', 'Cancelled'], true) && ! (int) ($r['is_deleted'] ?? 0))) {
            $blockers[] = 'Resolve pending payment vouchers for this property before transfer.';
        }
        if (array_filter($rows['invoices'], fn ($r) => in_array((int) $r['current_status_id'], [1, 8, 9], true) && substr((string) $r['returns_period'], 0, 10) >= $transfer->effective_date->toDateString())) {
            $blockers[] = 'Invoices already exist for the incoming ownership period. Cancel/reconcile them before transfer, then raise them for the new landlord.';
        }
        if (Schema::hasTable('temp_upload_batches') && DB::table('temp_upload_batches')->where('property_id', $property->id)->whereIn('status', ['staged', 'validated', 'ready', 'uploaded'])->exists()) {
            $blockers[] = 'Submit or cancel the unfinished bulk upload first.';
        }
        if (Schema::hasTable('tenant_reviews') && DB::table('tenant_reviews')->whereIn('lease_id', array_column($rows['leases'], 'id'))->whereNotIn('stage', ['approved', 'rejected'])->exists()) {
            $blockers[] = 'Complete pending tenant onboarding reviews before transfer.';
        }
        if (Schema::hasTable('cb_returns_merge_batch_items') && DB::table('cb_returns_merge_batch_items')->where('property_id', $property->id)->exists()) {
            $blockers[] = 'Remove this property from its merged-return batch before transfer. Configure a new batch for the incoming landlord after completion.';
        }
        if (Schema::hasTable('sys_pm_recoveries') && DB::table('sys_pm_recoveries')->where('property_id', $property->id)->where('current_status_id', 1)->exists()) {
            $blockers[] = 'Resolve pending recoveries before transfer.';
        }
        $allocated = collect($rows['payment_allocations'])->groupBy('payment_id')->map(fn ($group) => $group->sum(fn ($row) => $this->cents($row['amount_allocated'])));
        $unallocated = array_sum(array_map(fn ($payment) => max(0, $this->cents($payment['amount']) - $allocated->get($payment['id'], 0)), $rows['payments']));
        $summary = [
            'ledger_balance' => number_format($balance / 100, 2, '.', ''),
            'outstanding_invoices' => count($openInvoices),
            'tenant_arrears' => number_format(array_sum(array_map(fn ($r) => $this->cents($r['current_balance'] ?? $r['total_amount']), $openInvoices)) / 100, 2, '.', ''),
            'deposit_records' => count($rows['deposits']),
            'unallocated_receipts' => number_format($unallocated / 100, 2, '.', ''),
            'active_leases' => count(array_filter($rows['leases'], fn ($r) => ($r['lease_status'] ?? '') === 'Active')),
            'blockers' => $blockers,
        ];

        return ['summary' => $summary, 'rows' => $rows, 'open_invoice_ids' => array_column($openInvoices, 'id'),
            'eligible_invoice_ids' => array_keys(array_filter($invoiceOwners, fn ($owner) => $owner === (int) $transfer->from_client_id)), 'invoice_owners' => $invoiceOwners];
    }

    public function review(PropertyOwnershipTransfer $transfer, User $user, string $notes): void
    {
        $this->access($user, 'review');
        $this->visible($transfer, $user);
        DB::transaction(function () use ($transfer, $user, $notes) {
            $transfer = PropertyOwnershipTransfer::lockForUpdate()->findOrFail($transfer->id);
            $this->check(in_array($transfer->status, ['draft', 'needs_review'], true), 'transfer', 'This transfer is not awaiting Finance review.');
            $this->check((int) $transfer->created_by !== (int) $user->id || PropertyTransferAccess::isDirector($user), 'transfer', 'A different Finance user must reconcile this handover. Only a Director may reconcile their own request.');
            $snapshot = $this->snapshot($transfer);
            $this->check(! $snapshot['summary']['blockers'], 'transfer', implode(' ', $snapshot['summary']['blockers']));
            $transfer->update(['snapshot' => $snapshot, 'snapshot_hash' => $this->hash($snapshot), 'status' => 'reviewed',
                'reviewed_by' => $user->id, 'reviewed_at' => now(), 'finance_notes' => $notes, 'approved_by' => null, 'approved_at' => null]);
            $this->record($transfer, $user, PropertyTransferAccess::isDirector($user) ? 'finance_reviewed_by_director' : 'finance_reviewed', $notes);
        });
    }

    public function approve(PropertyOwnershipTransfer $transfer, User $user, string $notes): void
    {
        $this->access($user, 'approve');
        $this->visible($transfer, $user);
        DB::transaction(function () use ($transfer, $user, $notes) {
            $transfer = PropertyOwnershipTransfer::lockForUpdate()->findOrFail($transfer->id);
            $this->check($transfer->status === 'reviewed', 'transfer', 'Finance or a Director must review the handover first.');
            $this->check((int) $transfer->reviewed_by !== (int) $user->id || PropertyTransferAccess::isDirector($user), 'transfer', 'The Finance reviewer cannot also give final approval. Only a Director may do both.');
            $this->check(hash_equals($transfer->snapshot_hash, $this->hash($this->snapshot($transfer))), 'transfer', 'The handover figures changed. Request a fresh Finance review.');
            $transfer->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            $this->record($transfer, $user, 'approved', $notes);
        });
    }

    public function reset(PropertyOwnershipTransfer $transfer, User $user, string $notes, bool $cancel = false): void
    {
        $this->access($user, 'create');
        $this->visible($transfer, $user);
        DB::transaction(function () use ($transfer, $user, $notes, $cancel) {
            $transfer = PropertyOwnershipTransfer::lockForUpdate()->findOrFail($transfer->id);
            $this->check(! in_array($transfer->status, ['completed', 'cancelled'], true), 'transfer', 'A completed or cancelled transfer cannot be changed.');
            $transfer->update(['status' => $cancel ? 'cancelled' : 'needs_review', 'pending_property_id' => $cancel ? null : $transfer->property_id,
                'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null]);
            $this->record($transfer, $user, $cancel ? 'cancelled' : 'review_requested', $notes);
        });
    }

    public function complete(PropertyOwnershipTransfer $transfer, User $user): void
    {
        $this->access($user, 'execute');
        $this->visible($transfer, $user);
        DB::transaction(function () use ($transfer, $user) {
            Property::whereKey($transfer->property_id)->lockForUpdate()->firstOrFail();
            // Financial writers do not all lock the property. Briefly block writes while verifying and applying the handover.
            if (DB::getDriverName() === 'pgsql') {
                $tables = ['sys_pm_properties', 'sys_pm_units', 'sys_pm_clients', 'sys_pm_service_agreements', 'sys_pm_lease_agreements', 'cb_landlord_account',
                    'sys_pm_debtors_invoices', 'sys_pm_tenants_payments', 'sys_pm_tenants_accounts_trans', 'sys_pm_tenants_payments_invoices',
                    'sys_pm_deposits', 'sys_pm_deposit_allocations', 'cb_returns_summary', 'sys_pm_clients_advances_requisitions',
                    'sys_fin_reccurent_client_remittances', 'clients_bank_details', 'property_bank_details', 'sys_pm_clients_17_repeat',
                    'sys_eports_returns_batches_configurations_298_repeat', 'sys_eports_returns_batches_configurations_298_repeat_repeat_prs',
                    'cb_sla_invoiceable_items', 'sys_pm_service_agreements_186_repeat', 'sys_pm_service_agreements_24_repeat',
                    'sys_pm_service_agreements_282_repeat', 'sys_pm_service_agreements_282_repeat_repeat_return_items'];
                foreach (['temp_upload_batches', 'tenant_reviews', 'sys_pm_recoveries', 'cb_returns_merge_batch_items', 'cb_payment_vouchers_lines_new', 'cb_payment_vouchers_headers_new'] as $optional) {
                    if (Schema::hasTable($optional)) {
                        $tables[] = $optional;
                    }
                }
                sort($tables);
                DB::statement('LOCK TABLE '.implode(', ', $tables).' IN SHARE ROW EXCLUSIVE MODE');
            }
            $transfer = PropertyOwnershipTransfer::lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status === 'completed') {
                return;
            } // Retrying cannot duplicate the SLA or assignments.
            $this->check($transfer->status === 'approved', 'transfer', 'Approve this transfer before completing it.');
            $this->check($transfer->effective_date->isSameDay(today()), 'transfer', 'Complete the transfer on its effective date. If that date was missed, cancel and create a request with a new date.');
            $snapshot = $this->snapshot($transfer);
            $this->check(hash_equals($transfer->snapshot_hash, $this->hash($snapshot)), 'transfer', 'The handover changed after review. Request a fresh Finance review and approval before completing it.');
            $this->check(! $snapshot['summary']['blockers'], 'transfer', implode(' ', $snapshot['summary']['blockers']));
            $property = Property::lockForUpdate()->findOrFail($transfer->property_id);
            $old = ServiceAgreement::lockForUpdate()->findOrFail($transfer->old_sla_id);
            $old->update(['date_end' => $transfer->effective_date->copy()->subDay()->toDateString()]);
            $bank = ClientBankDetail::findOrFail($transfer->bank_detail_id);
            // Both the current property-bank screen and the older payout configuration must name the same payee.
            $legacyBank = ClientBankAccount::firstOrCreate([
                'parent_id' => $transfer->to_client_id, 'bank_id' => $bank->bank_id,
                'account_number' => $bank->account_number, 'account_name' => $bank->account_name, 'return_percentage' => 100,
            ], ['branch' => $bank->branch_id]);
            $property->update(['client_id' => $transfer->to_client_id, 'bank_account_id' => $legacyBank->id]);
            DB::table('property_bank_details')->where('property_id', $property->id)->delete();
            DB::table('property_bank_details')->insert(['property_id' => $property->id, 'client_id' => $transfer->to_client_id,
                'client_bank_id' => $transfer->bank_detail_id, 'added_by' => $user->id, 'added_on' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('sys_eports_returns_batches_configurations_298_repeat_repeat_prs')->where('prs', $property->id)->delete();
            $payoutConfig = ReturnsBatchConfiguration::firstOrCreate(['bank_account_id' => $legacyBank->id]);
            ReturnsBatchConfigPropertyMapping::create(['parent_id' => $payoutConfig->id, 'prs' => $property->id]);
            $sla = ServiceAgreement::create(array_merge($transfer->terms, [
                'date_time' => now(), 'property_id' => $property->id, 'client_d' => $transfer->to_client_id,
                'date_start' => $transfer->effective_date->toDateString(),
                'date_end' => $transfer->effective_date->copy()->addYears((int) $transfer->terms['years'])->toDateString(),
                'reference_number' => $transfer->reference.'-SLA', 'created_by_id' => $user->id,
                'current_status_id' => ServiceAgreement::STATUS_PENDING, 'charge_registration_fee' => false,
            ]));
            $this->copyChildren($old->id, $sla->id);
            $result = app(ServiceAgreementApprovalService::class)->approve([$sla->id], $user->id);
            $this->check(in_array($sla->id, $result['done'], true), 'transfer', $result['skipped'][$sla->id] ?? 'The new SLA could not be approved. Nothing was transferred.');
            foreach ([$transfer->from_client_id => 'Closing', $transfer->to_client_id => 'Opening'] as $clientId => $label) {
                $client = Client::withoutGlobalScopes()->findOrFail($clientId);
                LandlordAccount::create([
                    'client_id' => $clientId, 'property_id' => $property->id, 'landlord_code' => $client->reference_number,
                    'landlord_name' => mb_substr($client->names, 0, 60), 'property_code' => $property->reference_number,
                    'entry_date' => $transfer->effective_date, 'year' => $transfer->effective_date->year, 'month' => $transfer->effective_date->month,
                    'entry_type_code' => 'OWN', 'entry_description' => 'Ownership '.strtolower($label),
                    'transaction_number' => $transfer->reference.'-'.strtoupper($label),
                    'entry_particulars' => $label.' property account at ownership handover; no cash movement',
                    'debit_amount' => 0, 'credit_amount' => 0, 'total_amount' => 0, 'is_recovered' => 1,
                    'audit_user' => mb_substr($user->name, 0, 20), 'account_status' => 0, 'interest_rate' => 0,
                ]);
            }
            // Only operational links change. Signed lease files, invoices, receipts and ledger rows retain their history.
            DB::table('sys_pm_units')->where('property_id', $property->id)->update(['client_id' => $transfer->to_client_id]);
            $activeTenantIds = LeaseAgreement::where('property_id', $property->id)->where('lease_status', 'Active')->pluck('tenant_id');
            DB::table('sys_pm_clients')->whereIn('id', $activeTenantIds)->where('property_id', $property->id)
                ->update(['client_id' => $transfer->to_client_id, 'sla_id' => $sla->id]);
            LeaseAgreement::where('property_id', $property->id)->where('lease_status', 'Active')->update(['client_id' => $transfer->to_client_id, 'sla_id' => $sla->id]);
            DB::table('sys_pm_deposits')->where('property_id', $property->id)->where('client_id', $transfer->from_client_id)->update(['client_id' => $transfer->to_client_id, 'sla_id' => $sla->id]);
            DB::table('sys_fin_reccurent_client_remittances')->where('property_id', $property->id)->where('client_id', $transfer->from_client_id)->update(['client_id' => $transfer->to_client_id, 'sla_id' => $sla->id]);
            if ($transfer->arrears_policy === 'transfer') {
                foreach ($snapshot['rows']['invoices'] as $invoice) {
                    if (! in_array($invoice['id'], $snapshot['eligible_invoice_ids'])) {
                        continue;
                    }
                    DB::table('property_transfer_invoice_assignments')->insert([
                        'transfer_id' => $transfer->id, 'invoice_id' => $invoice['id'], 'client_id' => $transfer->to_client_id,
                        'sla_id' => $sla->id, 'effective_date' => $transfer->effective_date->toDateString(),
                        'balance_at_transfer' => $invoice['current_balance'] ?? $invoice['total_amount'],
                    ]);
                }
            }
            $transfer->update(['status' => 'completed', 'pending_property_id' => null, 'new_sla_id' => $sla->id, 'completed_at' => now()]);
            $this->record($transfer, $user, 'completed', 'Outgoing property account closed for new activity; incoming ownership opened. Other properties and historical records remain intact.', ['old_sla_id' => $old->id, 'new_sla_id' => $sla->id]);
        });
    }

    private function slaChildren(int $slaId): array
    {
        $children = [];
        foreach (['cb_sla_invoiceable_items' => 'sla_id', 'sys_pm_service_agreements_186_repeat' => 'parent_id',
            'sys_pm_service_agreements_24_repeat' => 'parent_id', 'sys_pm_service_agreements_282_repeat' => 'parent_id'] as $table => $key) {
            $children[$table] = DB::table($table)->where($key, $slaId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }
        $children['sys_pm_service_agreements_282_repeat_repeat_return_items'] = DB::table('sys_pm_service_agreements_282_repeat_repeat_return_items')
            ->whereIn('parent_id', array_column($children['sys_pm_service_agreements_282_repeat'], 'id'))->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        return $children;
    }

    private function copyChildren(int $old, int $new): void
    {
        $tables = $this->slaChildren($old);
        $parents = [];
        foreach ($tables as $table => $rows) {
            foreach ($rows as $row) {
                $id = $row['id'];
                unset($row['id']);
                foreach (['created_at', 'updated_at'] as $timestamp) {
                    if (array_key_exists($timestamp, $row)) {
                        $row[$timestamp] = now();
                    }
                }
                $key = $table === 'cb_sla_invoiceable_items' ? 'sla_id' : 'parent_id';
                $row[$key] = $table === 'sys_pm_service_agreements_282_repeat_repeat_return_items' ? $parents[$row[$key]] : $new;
                $newId = DB::table($table)->insertGetId($row);
                if ($table === 'sys_pm_service_agreements_282_repeat') {
                    $parents[$id] = $newId;
                }
            }
        }
    }

    public function record(PropertyOwnershipTransfer $transfer, ?User $user, string $action, string $notes, array $details = []): void
    {
        $transfer->events()->create(['actor_id' => $user?->id, 'action' => $action, 'notes' => $notes, 'details' => $details, 'created_at' => now()]);
    }

    private function check(bool $ok, string $field, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }
}
