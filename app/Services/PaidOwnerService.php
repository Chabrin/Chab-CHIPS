<?php

namespace App\Services;

use App\Exceptions\ClientAccountException;
use App\Models\GlAccountSetup;
use App\Models\Recovery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Paid Owner: a tenant paid the landlord directly, so the money never reached the company. A field officer records it
 * as a recovery; it takes effect only when the person who holds the confirmer seat (set on the paid_owner_confirmation
 * workflow under Accounting > Approvals, a role or named users) confirms it.
 *
 * Confirming does four things in one database transaction, so nothing is half done:
 *  - the tenant's invoice is paid down by the amount (cleared, status 9, or partly paid, status 8) and the tenant's
 *    ledger is credited with a PO row, as the old system did;
 *  - the client's ledger is debited under billing code 0007 (PAID OWNERS); the next return takes that debit as a
 *    deduction, which nets the rent line the invoice still produces, so the landlord is not paid twice;
 *  - when GL posting is on, Dr Clients Account / Cr Tenants Account (no bank is involved);
 *  - the recovery is marked Approved and posted.
 *
 * The invoice must never be closed with the recovery code 37 (Paid Owner): returns only read invoices in statuses
 * 1, 8 and 9, so a 37 invoice would drop out of the return while the debit is still taken, and the landlord would be
 * docked twice. It is paid down like any other payment, and the PO ledger row and the recovery record mark it.
 */
class PaidOwnerService
{
    const WORKFLOW_NAME = 'paid_owner_confirmation';
    const TYPE_WORKFLOW_STATUS = 37; // the Paid Owner row of sys_set_transactions_types
    const LEDGER_CODE = '0007'; // PAID OWNERS, already on the return deduction list
    const TRANSACTION_TYPE_CODE = '2032'; // item_code of the Paid Owner transaction type
    const PENDING = 1;
    const CONFIRMED = 5;
    const REJECTED = 41;
    const INVOICE_PARTIAL = 8;
    const INVOICE_CLEARED = 9;

    protected GlJournalPoster $gl;

    /**
     * @param GlJournalPoster $gl
     */
    public function __construct(GlJournalPoster $gl) {
        $this->gl = $gl;
    }

    /**
     * The id of the Paid Owner transaction type.
     */
    public function typeId(): ?int {
        $id = DB::table('sys_set_transactions_types')->where('workflow_status_id', self::TYPE_WORKFLOW_STATUS)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Whether a recovery is a paid-owner recovery.
     *
     * @param Recovery $recovery
     */
    public function isPaidOwner(Recovery $recovery): bool {
        $typeId = $this->typeId();

        return $typeId !== null && (int) $recovery->type_id === $typeId;
    }

    /**
     * Whether GL posting is switched on (the same switch as the invoice journals).
     */
    public function glEnabled(): bool {
        return (bool) config('accounting.post_invoice_journals', false);
    }

    /**
     * The seats of the confirmer workflow: the named users and the roles whose members may confirm.
     *
     * @return array{users: int[], roles: int[]}
     */
    public function confirmerSeats(): array {
        $rows = DB::table('workflow_approvers as a')
            ->join('approval_workflows as w', 'w.id', '=', 'a.workflow_id')
            ->where('w.workflow_name', self::WORKFLOW_NAME)
            ->where('a.is_active', 1)
            ->get(['a.approver_id', 'a.position_type', 'a.role_id']);

        $seats = ['users' => [], 'roles' => []];

        foreach ($rows as $row) {
            if ($row->position_type === 'role' && (int) $row->role_id > 0) {
                $seats['roles'][] = (int) $row->role_id;
            } elseif ($row->position_type === null && (int) $row->approver_id > 0) {
                $seats['users'][] = (int) $row->approver_id;
            }
        }

        return $seats;
    }

    /**
     * A readable name for who confirms, for the screens.
     */
    public function confirmerLabel(): string {
        $seats = $this->confirmerSeats();
        $names = [];

        if ($seats['roles']) {
            $names = array_merge($names, DB::table('cb_roles')->whereIn('id', $seats['roles'])->pluck('display_name')->all());
        }
        if ($seats['users']) {
            $names = array_merge($names, DB::table('josya_users')->whereIn('id', $seats['users'])->pluck('name')->all());
        }

        return $names ? implode(', ', $names) : 'nobody (not set)';
    }

    /**
     * The roles that can be chosen as the confirmer, for the Recovery page's setting.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function confirmerRoleOptions(): array {
        return DB::table('cb_roles')->orderBy('display_name')->get(['id', 'name', 'display_name'])
            ->map(fn ($role) => ['id' => (int) $role->id, 'name' => $role->display_name ?: $role->name])
            ->all();
    }

    /**
     * Make one role the only confirmer of paid-owner recoveries. Whoever held the seat before (named users or other
     * roles) is removed, and both steps go into the workflow change log the Approvals page shows. Doing it again with the
     * same role changes nothing.
     *
     * @param int $roleId the role that will confirm
     * @param int $userId the person changing the setting
     */
    public function setConfirmerRole(int $roleId, int $userId): void {
        $role = DB::table('cb_roles')->where('id', $roleId)->first();

        if (!$role) {
            throw new ClientAccountException('That role does not exist.');
        }

        $workflowId = DB::table('approval_workflows')->where('workflow_name', self::WORKFLOW_NAME)->value('id');

        if (!$workflowId) {
            throw new ClientAccountException('The paid-owner confirmation workflow is missing. Run the migrations.');
        }

        DB::transaction(function () use ($workflowId, $role, $userId) {
            $current = DB::table('workflow_approvers')->where('workflow_id', $workflowId)->get();

            if ($current->count() === 1 && $current[0]->position_type === 'role' && (int) $current[0]->role_id === (int) $role->id && (int) $current[0]->is_active === 1) {
                return;
            }

            $log = fn (string $action, string $details, ?int $oldLevel, ?int $newLevel) => DB::table('workflow_approvers_changes_log')->insert([
                'workflow_id' => $workflowId,
                'approver_id' => null,
                'action' => $action,
                'old_level' => $oldLevel,
                'new_level' => $newLevel,
                'old_is_final' => null,
                'new_is_final' => $newLevel ? 1 : null,
                'details' => $details,
                'changed_by' => $userId,
                'changed_on' => now(),
            ]);

            foreach ($current as $row) {
                $name = $row->position_type === 'role'
                    ? 'role "' . DB::table('cb_roles')->where('id', $row->role_id)->value('name') . '"'
                    : '"' . DB::table('josya_users')->where('id', $row->approver_id)->value('name') . '"';
                $log('APPROVER_REMOVED', 'Removed ' . $name . ' as paid-owner confirmer (from the Recovery page)', (int) $row->approval_level, null);
            }

            DB::table('workflow_approvers')->where('workflow_id', $workflowId)->delete();
            DB::table('workflow_approvers')->insert([
                'workflow_id' => $workflowId,
                'approver_id' => null,
                'approval_level' => 1,
                'is_final' => 1,
                'is_active' => 1,
                'added_by' => $userId,
                'added_on' => now(),
                'position_type' => 'role',
                'role_id' => $role->id,
            ]);
            $log('APPROVER_ADDED', 'Added role "' . $role->name . '" as paid-owner confirmer (from the Recovery page)', null, 1);
        });
    }

    /**
     * Whether a user holds the confirmer seat.
     *
     * @param int $userId
     */
    public function canConfirm(int $userId): bool {
        $seats = $this->confirmerSeats();

        if (in_array($userId, $seats['users'], true)) {
            return true;
        }

        return $seats['roles'] && DB::table('cb_role_user')->where('user_id', $userId)->whereIn('role_id', $seats['roles'])->exists();
    }

    /**
     * Why a user may not confirm or reject a recovery, or null when they may.
     *
     * @param Recovery $recovery
     * @param int $userId
     */
    public function refusal(Recovery $recovery, int $userId): ?string {
        if (!$this->isPaidOwner($recovery)) {
            return 'This is not a paid-owner recovery.';
        }
        if ((int) $recovery->current_status_id !== self::PENDING) {
            return 'This paid-owner recovery is no longer waiting for confirmation.';
        }
        if (!$this->confirmerSeats()['users'] && !$this->confirmerSeats()['roles']) {
            return 'No one is set to confirm paid-owner recoveries. Add the confirmer under Accounting > Approvals (paid_owner_confirmation).';
        }
        if (!$this->canConfirm($userId)) {
            return 'Only ' . $this->confirmerLabel() . ' can confirm a paid-owner recovery.';
        }
        if ((int) $recovery->created_by === $userId) {
            return 'You recorded this paid-owner recovery, so someone else must confirm it.';
        }

        return null;
    }

    /**
     * The landlord of a property the way a return works it out: the client on the property's latest service agreement,
     * and the client on the property itself when the agreement names none. The client ledger debit must carry this client,
     * or the return of the property would not pick it up.
     *
     * @param int $propertyId
     */
    public function landlordOf(int $propertyId): ?int {
        $fromSla = DB::table('sys_pm_service_agreements')
            ->where('property_id', $propertyId)
            ->orderByRaw('date_start DESC NULLS LAST')
            ->orderByDesc('id')
            ->value('client_d');

        $id = (int) ($fromSla ?: DB::table('sys_pm_properties')->where('id', $propertyId)->value('client_id'));

        return $id > 0 ? $id : null;
    }

    /**
     * What is still owed on an invoice.
     *
     * @param object $invoice a sys_pm_debtors_invoices row
     */
    public function openBalance(object $invoice): float {
        return round((float) ($invoice->current_balance ?? $invoice->total_amount), 2);
    }

    /**
     * Check a paid-owner record before it is saved: it must name a tenant invoice of that tenant, unit and property that
     * is still open, and the amount must fit the balance after the other paid-owner recoveries still waiting on it.
     * Throws a ClientAccountException whose message is safe to show.
     *
     * @param array $data type_id, client_id, property_id, unit_id, tenant_id, offset_invoice_id, amount
     * @param int|null $ignoreRecoveryId the recovery being edited, left out of the waiting total
     */
    public function assertRecordable(array $data, ?int $ignoreRecoveryId = null): void {
        foreach (['tenant_id' => 'tenant', 'unit_id' => 'unit', 'offset_invoice_id' => 'invoice'] as $key => $label) {
            if (empty($data[$key])) {
                throw new ClientAccountException('A paid-owner recovery needs the ' . $label . ' that was paid.');
            }
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new ClientAccountException('The amount must be more than zero.');
        }

        $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $data['offset_invoice_id'])->first();
        if (!$invoice || (string) $invoice->debtor_type_id !== '2') {
            throw new ClientAccountException('The invoice is not a tenant invoice.');
        }
        if ((int) $invoice->tenant_id !== (int) $data['tenant_id'] || (int) $invoice->unit_id !== (int) $data['unit_id'] || (int) $invoice->property_id !== (int) $data['property_id']) {
            throw new ClientAccountException('The invoice does not belong to that tenant, unit and property.');
        }
        if ($this->landlordOf((int) $data['property_id']) !== (int) $data['client_id']) {
            throw new ClientAccountException('The property does not belong to that client.');
        }
        if (!in_array((int) $invoice->current_status_id, [1, self::INVOICE_PARTIAL], true)) {
            throw new ClientAccountException('The invoice is not open, so it cannot be paid down.');
        }

        $waiting = (float) DB::table('sys_pm_recoveries')
            ->where('offset_invoice_id', $invoice->id)
            ->where('type_id', $data['type_id'])
            ->where('current_status_id', self::PENDING)
            ->when($ignoreRecoveryId, fn ($query) => $query->where('id', '!=', $ignoreRecoveryId))
            ->sum('amount');
        $available = round($this->openBalance($invoice) - $waiting, 2);

        if ($amount > $available + 0.005) {
            throw new ClientAccountException('The amount (' . number_format($amount, 2) . ') is more than what is left to pay on the invoice (' . number_format(max($available, 0), 2) . ', after other paid-owner recoveries waiting for confirmation).');
        }
    }

    /**
     * Confirm a paid-owner recovery: pay the invoice down, credit the tenant ledger, debit the client ledger, post the GL
     * journal when it is on, and mark the recovery Approved and posted. All or nothing.
     *
     * @param Recovery $recovery
     * @param int $userId the confirmer
     * @return array{invoice_status: int, invoice_balance: float, amount: float}
     */
    public function confirm(Recovery $recovery, int $userId): array {
        return DB::transaction(function () use ($recovery, $userId) {
            $locked = Recovery::whereKey($recovery->id)->lockForUpdate()->firstOrFail();

            if ($message = $this->refusal($locked, $userId)) {
                throw new ClientAccountException($message);
            }

            $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $locked->offset_invoice_id)->lockForUpdate()->first();
            $amount = round((float) $locked->amount, 2);
            $balance = $invoice ? $this->openBalance($invoice) : 0.0;

            if (!$invoice || !in_array((int) $invoice->current_status_id, [1, self::INVOICE_PARTIAL], true)) {
                throw new ClientAccountException('The invoice is no longer open, so this paid-owner recovery cannot be confirmed.');
            }
            if ($amount > $balance + 0.005) {
                throw new ClientAccountException('The amount (' . number_format($amount, 2) . ') is now more than what is left to pay on the invoice (' . number_format($balance, 2) . ').');
            }

            $newBalance = round(max($balance - $amount, 0), 2);
            $status = $newBalance <= 0.005 ? self::INVOICE_CLEARED : self::INVOICE_PARTIAL;

            DB::table('sys_pm_debtors_invoices')->where('id', $invoice->id)->update([
                'current_balance' => $newBalance,
                'current_status_id' => $status,
            ]);

            $this->creditTenantLedger($locked, $invoice, $amount);
            $this->debitClientLedger($locked, $invoice, $amount, $userId);
            $this->postGl($locked, $invoice, $amount, $userId);

            $locked->current_status_id = self::CONFIRMED;
            $locked->actioned_by = $userId;
            $locked->actioned_at = now();
            $locked->posted_to_landlord_at = now();
            $locked->save();

            Log::info('PaidOwnerService: paid-owner recovery confirmed', ['recovery_id' => $locked->id, 'invoice_id' => $invoice->id, 'amount' => $amount, 'by' => $userId]);

            return ['invoice_status' => $status, 'invoice_balance' => $newBalance, 'amount' => $amount];
        });
    }

    /**
     * Reject a paid-owner recovery. Nothing is posted.
     *
     * @param Recovery $recovery
     * @param int $userId the person rejecting
     * @param string $reason why, required
     */
    public function reject(Recovery $recovery, int $userId, string $reason): void {
        $reason = trim($reason);

        if ($reason === '') {
            throw new ClientAccountException('Give the reason for rejecting.');
        }

        DB::transaction(function () use ($recovery, $userId, $reason) {
            $locked = Recovery::whereKey($recovery->id)->lockForUpdate()->firstOrFail();

            if ($message = $this->refusal($locked, $userId)) {
                throw new ClientAccountException($message);
            }

            $locked->current_status_id = self::REJECTED;
            $locked->actioned_by = $userId;
            $locked->actioned_at = now();
            $locked->rejection_reason = mb_substr($reason, 0, 500);
            $locked->save();
        });
    }

    /**
     * Credit the tenant's ledger with the amount paid to the owner, one PO row like the old system, tied to the invoice.
     *
     * @param Recovery $recovery
     * @param object $invoice the invoice row
     * @param float $amount
     */
    protected function creditTenantLedger(Recovery $recovery, object $invoice, float $amount): void {
        $item = InvoiceBillingService::itemName($invoice);

        DB::table('sys_pm_tenants_accounts_trans')->insert([
            'date_time' => $recovery->recovery_date ?: now(),
            'narration' => $invoice->narration ?: $item,
            'pay_for' => $item,
            'dr_cr' => 'CR',
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'receipt_number' => null,
            'payment_mode' => 'PO',
            'entry_ref' => mb_substr((string) $recovery->ref_number, 0, 100),
            'entry_by' => (string) $recovery->created_by,
            'invoice_number' => $invoice->reference_number,
            'invoice_id' => $invoice->id,
            'payment_id' => null,
            'zone_id' => $invoice->zone_id,
            'property_id' => $invoice->property_id,
            'unit_id' => $invoice->unit_id,
            'lease_id' => $invoice->lease_id,
            'client_id' => $recovery->client_id,
            'tenant_id' => $invoice->tenant_id,
            'sla_id' => $invoice->sla_id,
            'field_officer_id' => $invoice->field_officer_id,
            'zone_supervisor_id' => $invoice->zone_supervisor_id,
            'zone_manager_id' => $invoice->zone_manager_id,
            'current_status_id' => 1,
        ]);
    }

    /**
     * Debit the client's ledger under code 0007 (PAID OWNERS). It is left unrecovered: the next return takes it as a
     * deduction and flags it recovered when the return is finally approved.
     *
     * @param Recovery $recovery
     * @param object $invoice the invoice row
     * @param float $amount
     * @param int $userId the confirmer
     */
    protected function debitClientLedger(Recovery $recovery, object $invoice, float $amount, int $userId): void {
        $clientId = $this->landlordOf((int) $recovery->property_id) ?: (int) $recovery->client_id;
        $codes = DB::table('sys_pm_clients as c')
            ->crossJoin('sys_pm_properties as p')
            ->where('c.id', $clientId)
            ->where('p.id', $recovery->property_id)
            ->select('c.reference_number as landlord_code', 'c.names as landlord_name', 'p.reference_number as property_code')
            ->first();

        if (!$codes) {
            throw new ClientAccountException('The client or property of paid-owner recovery ' . $recovery->ref_number . ' could not be found.');
        }

        $tenant = (string) DB::table('sys_pm_clients')->where('id', $invoice->tenant_id)->value('names');
        $unit = (string) DB::table('sys_pm_units')->where('id', $invoice->unit_id)->value('unit_code');
        $particulars = 'Paid owner ' . $recovery->ref_number . ' - ' . trim($tenant . ' ' . $unit . ' ' . $invoice->narration);
        $user = DB::table('josya_users')->where('id', $userId)->value('name');

        DB::table('cb_landlord_account')->insert([
            'year' => now()->year,
            'month' => now()->month,
            'landlord_code' => $codes->landlord_code,
            'landlord_name' => $codes->landlord_name,
            'property_code' => $codes->property_code,
            'transaction_number' => mb_substr((string) $recovery->ref_number, 0, 50),
            'entry_type_code' => self::LEDGER_CODE,
            'entry_description' => 'PAID OWNER',
            'entry_particulars' => mb_substr($particulars, 0, 200),
            'entry_date' => now(),
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'total_amount' => $amount,
            'gl_account' => 'EXPENSE',
            'vendor_id' => $codes->landlord_code,
            'is_recovered' => 0,
            'client_id' => $clientId,
            'property_id' => $recovery->property_id,
            'audit_user' => mb_substr((string) ($user ?: $userId), 0, 20),
        ]);
    }

    /**
     * Post Dr Clients Account / Cr Tenants Account when GL posting is on. The tenant owes less and the company owes the
     * landlord less, because the landlord already has the cash. No bank account is touched. Posting the same recovery
     * twice does nothing.
     *
     * @param Recovery $recovery
     * @param object $invoice the invoice row
     * @param float $amount
     * @param int $userId the confirmer
     */
    protected function postGl(Recovery $recovery, object $invoice, float $amount, int $userId): void {
        if (!$this->glEnabled()) {
            return;
        }

        $details = 'recovery_id:' . $recovery->id . ':paid_owner';

        if ($this->gl->exists($details)) {
            return;
        }

        $setup = GlAccountSetup::first();
        $clients = $setup->clients_account_id ?? null;
        $tenants = $setup->tenants_account_id ?? null;

        if (!$clients || !$tenants) {
            throw new ClientAccountException('The GL setup is missing the Clients Account or the Tenants Account, so the paid-owner recovery cannot be posted.');
        }

        $this->gl->journal(
            (string) $recovery->ref_number,
            'Paid owner - ' . $invoice->reference_number,
            $details,
            [
                ['account_id' => (int) $clients, 'type' => 'Debit', 'amount' => $amount],
                ['account_id' => (int) $tenants, 'type' => 'Credit', 'amount' => $amount],
            ],
            $userId,
            self::TRANSACTION_TYPE_CODE
        );
    }
}
