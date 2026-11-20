<?php

namespace App\Services;

use App\Exceptions\ClientAccountException;
use App\Models\GlAccountSetup;
use App\Models\Recovery;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Recoveries of rent that was paid to the landlord but never reached the company. There are five reasons: Paid Owner
 * (the tenant paid the landlord directly), Sat Deposit (the tenant moved out owing rent), Overpaid (the landlord was
 * paid before the tenant paid), Absconded and Paid Vacant (the tenant left, or the unit was vacant or paid in error).
 * A field officer records one against the tenant's invoice; it takes effect only when the person who holds the
 * confirmer seat (set on the paid_owner_confirmation workflow under Accounting > Approvals, a role or named users)
 * confirms it, and never the person who recorded it.
 *
 * Confirming does five things in one database transaction, so nothing is half done:
 *  - the tenant's invoice is paid down by the amount (cleared, status 9, or partly paid, status 8) and the tenant's
 *    ledger is credited, as the old system did: mode PO for Paid Owner, mode REC for the other reasons;
 *  - the tenant gets a receipt: one row in the receipts table (sys_pm_tenants_payments_allocated) numbered with the
 *    recovery reference, as the old system did. No payment row is made, because no money came in;
 *  - the client's ledger is debited: code 0007 (PAID OWNERS) for Paid Owner, code 0024 (the Rent recovery code the
 *    returns already take) for the others; the next return takes that debit as a deduction, which nets the rent line
 *    the invoice still produces, so the landlord is not paid twice;
 *  - when GL posting is on, Dr Clients Account / Cr Tenants Account (no bank is involved);
 *  - the recovery is marked Approved and posted.
 *
 * The invoice must never be closed with a recovery code (34 to 37): returns only read invoices in statuses 1, 8 and 9,
 * so such an invoice would drop out of the return while the debit is still taken, and the landlord would be docked
 * twice. It is paid down like any other payment, and the ledger row and the recovery record mark it.
 *
 * Commission is not touched. The old system never reversed it for any of the five reasons (997 of 1,028 returns with
 * Absconded or Paid Vacant lines took the full commission), so a reversal would be a new rule that is not built.
 */
class RecoveryService
{
    const WORKFLOW_NAME = 'paid_owner_confirmation';
    const PAID_OWNER_CODE = '2032'; // item_code of the Paid Owner transaction type
    const PENDING = 1;
    const CONFIRMED = 5;
    const REJECTED = 41;
    const INVOICE_PARTIAL = 8;
    const INVOICE_CLEARED = 9;
    const PROOF_DIRECTORY = 'recovery_proofs';
    const REFERENCE_PREFIX = 'REC';
    const REFERENCE_LOCK_KEY = 7340012; // next to the voucher sequence's key, a different lock

    /**
     * The five reasons, keyed by the item_code of their row in sys_set_transactions_types. ledger_code is the billing code
     * of the client ledger debit, ledger_mode the payment mode of the tenant ledger credit and of the receipt (the old
     * system used PO for Paid Owner and REC, a recovery receipt, for the rest), and proof_required whether a PDF proof
     * must be attached before it can be confirmed.
     */
    const REASONS = [
        '2029' => ['key' => 'paid_vacant', 'label' => 'Paid Vacant', 'ledger_code' => '0024', 'ledger_mode' => 'REC', 'proof_required' => false, 'placeholder' => 'The landlord was paid for a unit that was vacant, or paid in error, for...'],
        '2030' => ['key' => 'sat_deposit', 'label' => 'Sat Deposit', 'ledger_code' => '0024', 'ledger_mode' => 'REC', 'proof_required' => false, 'placeholder' => 'The tenant moved out owing rent for...'],
        '2031' => ['key' => 'absconded', 'label' => 'Absconded', 'ledger_code' => '0024', 'ledger_mode' => 'REC', 'proof_required' => false, 'placeholder' => 'The tenant left without notice owing rent for...'],
        '2032' => ['key' => 'paid_owner', 'label' => 'Paid Owner', 'ledger_code' => '0007', 'ledger_mode' => 'PO', 'proof_required' => true, 'placeholder' => 'The tenant paid the owner directly for...'],
        '2079' => ['key' => 'overpaid', 'label' => 'Overpaid', 'ledger_code' => '0024', 'ledger_mode' => 'REC', 'proof_required' => false, 'placeholder' => 'The landlord was paid before the tenant paid, for...'],
    ];

    protected GlJournalPoster $gl;

    /**
     * The reasons that exist as transaction types, keyed by type id, read once per instance.
     *
     * @var array<int, array>|null
     */
    protected ?array $reasonTypes = null;

    /**
     * @param GlJournalPoster $gl
     */
    public function __construct(GlJournalPoster $gl) {
        $this->gl = $gl;
    }

    /**
     * The next recovery reference, REC-<year>-<number> with the number padded to five digits and restarting each year
     * (REC-2026-00001). It is the highest number already saved for that year plus one, taken while holding a database
     * lock, so two people saving at the same moment cannot get the same one. It must be called inside an open
     * transaction, and the recovery must be saved in that same transaction: the lock is held until it ends.
     *
     * @param int|null $year defaults to the current year
     */
    public function nextReference(?int $year = null): string {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('nextReference() must be called inside a database transaction.');
        }

        // Postgres only (production and dev). The SQLite test database has a single connection, so there is nothing to wait for.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::REFERENCE_LOCK_KEY]);
        }

        $year = $year ?: now()->year;
        $prefix = self::REFERENCE_PREFIX . '-' . $year . '-';

        // The longest then the highest: five digits sort alike as text, but 100000 must come after 99999
        $last = DB::table('sys_pm_recoveries')
            ->where('ref_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(ref_number) DESC')
            ->orderByDesc('ref_number')
            ->value('ref_number');

        $number = $last && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $last, $match) ? ((int) $match[1]) + 1 : 1;

        return $prefix . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * The five reasons that exist as transaction types, keyed by type id. Each carries its registry entry plus type_id,
     * type_name and commission ('Yes' or 'No', the commissionable flag of the transaction type).
     *
     * @return array<int, array>
     */
    public function reasonTypes(): array {
        if ($this->reasonTypes !== null) {
            return $this->reasonTypes;
        }

        $this->reasonTypes = [];

        $rows = DB::table('sys_set_transactions_types')->whereIn('item_code', array_keys(self::REASONS))->get(['id', 'type_name', 'item_code', 'is_commissionable']);

        foreach ($rows as $row) {
            $this->reasonTypes[(int) $row->id] = self::REASONS[(string) $row->item_code] + [
                'item_code' => (string) $row->item_code,
                'type_id' => (int) $row->id,
                'type_name' => $row->type_name,
                'commission' => $row->is_commissionable === 'Yes' ? 'Yes' : 'No',
            ];
        }

        return $this->reasonTypes;
    }

    /**
     * The ids of the transaction types that are one of the five reasons.
     *
     * @return int[]
     */
    public function reasonTypeIds(): array {
        return array_keys($this->reasonTypes());
    }

    /**
     * The reason a transaction type stands for, or null when it is not one of the five.
     *
     * @param int $typeId
     */
    public function reasonOfType(int $typeId): ?array {
        return $this->reasonTypes()[$typeId] ?? null;
    }

    /**
     * The reason of a recovery, or null when its type is not one of the five (it then follows the old approve and post flow).
     *
     * @param Recovery $recovery
     */
    public function reasonOf(Recovery $recovery): ?array {
        return $this->reasonOfType((int) $recovery->type_id);
    }

    /**
     * Whether a recovery is one of the five reasons, confirmed on the Recovery page.
     *
     * @param Recovery $recovery
     */
    public function isInvoiceRecovery(Recovery $recovery): bool {
        return $this->reasonOf($recovery) !== null;
    }

    /**
     * Whether a recovery is a paid-owner recovery.
     *
     * @param Recovery $recovery
     */
    public function isPaidOwner(Recovery $recovery): bool {
        return ($this->reasonOf($recovery)['item_code'] ?? null) === self::PAID_OWNER_CODE;
    }

    /**
     * What the recovery form needs to know about each reason, keyed by type id, as the script reads it.
     *
     * @return array<int, array{label: string, commission: string, commission_note: string, proof_required: bool, placeholder: string}>
     */
    public function reasonOptions(): array {
        $options = [];

        foreach ($this->reasonTypes() as $typeId => $reason) {
            $options[$typeId] = [
                'label' => $reason['label'],
                'commission' => $reason['commission'],
                'commission_note' => $reason['commission'] === 'Yes'
                    ? 'Set by the reason: commission is kept on this rent.'
                    : 'Set by the reason. The commission is still worked out on the rent as before; reversing it is not switched on.',
                'proof_required' => $reason['proof_required'],
                'placeholder' => $reason['placeholder'],
            ];
        }

        return $options;
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
     * A paid-owner recovery needs its proof document (a PDF): one must be attached now or already be on the record.
     * Throws a ClientAccountException whose message is safe to show. The other reasons do not need one (they may attach one).
     *
     * @param array $data the form data, with type_id
     * @param Recovery|null $existing the recovery being edited, null when it is new
     * @param UploadedFile|null $file the file sent with the form
     */
    public function assertProofGiven(array $data, ?Recovery $existing, ?UploadedFile $file): void {
        $reason = $this->reasonOfType((int) ($data['type_id'] ?? 0));

        if ($reason === null || !$reason['proof_required']) {
            return;
        }

        if (!$file && !($existing && $existing->proof_path)) {
            throw new ClientAccountException('Attach the proof document (a PDF of the landlord\'s signed acknowledgement or receipt).');
        }
    }

    /**
     * The commissionable flag a recovery gets from its reason ('Yes' or 'No', from the transaction type), or null when the
     * type is not one of the five and the person chooses it on the form.
     *
     * @param int $typeId
     */
    public function commissionFlag(int $typeId): ?string {
        return $this->reasonOfType($typeId)['commission'] ?? null;
    }

    /**
     * Attach (or replace) the proof document of a recovery that is still Pending. The file goes on the private local
     * disk, the old file is removed, and who attached it and when is recorded on the recovery. Once a recovery is
     * confirmed or rejected its document is fixed.
     *
     * @param Recovery $recovery
     * @param UploadedFile $file the PDF
     * @param int $userId
     * @return string the stored path
     */
    public function attachProof(Recovery $recovery, UploadedFile $file, int $userId): string {
        if ((int) $recovery->current_status_id !== self::PENDING) {
            throw new ClientAccountException('The document can only be changed while the recovery is still Pending.');
        }

        $oldPath = $recovery->proof_path;
        $path = $file->store(self::PROOF_DIRECTORY . '/' . $recovery->id, 'local');

        // If the record cannot be updated, the file just stored must not be left behind with nothing pointing at it
        try {
            $recovery->update([
                'proof_path' => $path,
                'proof_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'proof_uploaded_by' => $userId,
                'proof_uploaded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }

        if ($oldPath && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        return $path;
    }

    /**
     * Whether a user may open a recovery's proof document: whoever may read recoveries, the person who recorded it, and
     * whoever holds the confirmer seat.
     *
     * @param Recovery $recovery
     * @param int $userId
     * @param bool $canRead whether the user holds read_recoveries
     */
    public function canViewProof(Recovery $recovery, int $userId, bool $canRead): bool {
        return $canRead || (int) $recovery->created_by === $userId || $this->canConfirm($userId);
    }

    /**
     * Why a user may not confirm or reject a recovery, or null when they may.
     *
     * @param Recovery $recovery
     * @param int $userId
     */
    public function refusal(Recovery $recovery, int $userId): ?string {
        if (!$this->isInvoiceRecovery($recovery)) {
            return 'This recovery is not confirmed here: it is approved on the Property Confirmations page.';
        }
        if ((int) $recovery->current_status_id !== self::PENDING) {
            return 'This recovery is no longer waiting for confirmation.';
        }
        if (!$this->confirmerSeats()['users'] && !$this->confirmerSeats()['roles']) {
            return 'No one is set to confirm recoveries. Add the confirmer under Accounting > Approvals (paid_owner_confirmation).';
        }
        if (!$this->canConfirm($userId)) {
            return 'Only ' . $this->confirmerLabel() . ' can confirm a recovery.';
        }
        if ((int) $recovery->created_by === $userId) {
            return 'You recorded this recovery, so someone else must confirm it.';
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
     * Check a recovery of one of the five reasons before it is saved: it must name a tenant invoice of that tenant, unit
     * and property that is still open, and the amount must fit the balance after the other recoveries still waiting on
     * that invoice. Throws a ClientAccountException whose message is safe to show.
     *
     * @param array $data type_id, client_id, property_id, unit_id, tenant_id, offset_invoice_id, amount
     * @param int|null $ignoreRecoveryId the recovery being edited, left out of the waiting total
     */
    public function assertRecordable(array $data, ?int $ignoreRecoveryId = null): void {
        foreach (['tenant_id' => 'tenant', 'unit_id' => 'unit', 'offset_invoice_id' => 'invoice'] as $key => $label) {
            if (empty($data[$key])) {
                throw new ClientAccountException('A recovery needs the ' . $label . ' it settles.');
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
            ->whereIn('type_id', $this->reasonTypeIds())
            ->where('current_status_id', self::PENDING)
            ->when($ignoreRecoveryId, fn ($query) => $query->where('id', '!=', $ignoreRecoveryId))
            ->sum('amount');
        $available = round($this->openBalance($invoice) - $waiting, 2);

        if ($amount > $available + 0.005) {
            throw new ClientAccountException('The amount (' . number_format($amount, 2) . ') is more than what is left to pay on the invoice (' . number_format(max($available, 0), 2) . ', after other recoveries waiting for confirmation).');
        }
    }

    /**
     * Confirm a recovery: pay the invoice down, credit the tenant ledger, give the tenant a receipt, debit the client
     * ledger, post the GL journal when it is on, and mark the recovery Approved and posted. All or nothing.
     *
     * @param Recovery $recovery
     * @param int $userId the confirmer
     * @return array{invoice_status: int, invoice_balance: float, amount: float, receipt: string}
     */
    public function confirm(Recovery $recovery, int $userId): array {
        return DB::transaction(function () use ($recovery, $userId) {
            $locked = Recovery::whereKey($recovery->id)->lockForUpdate()->firstOrFail();

            if ($message = $this->refusal($locked, $userId)) {
                throw new ClientAccountException($message);
            }

            $reason = $this->reasonOf($locked);

            if ($reason['proof_required'] && !$locked->proof_path) {
                throw new ClientAccountException('This paid-owner recovery has no proof document. It must be attached before it can be confirmed.');
            }

            $invoice = DB::table('sys_pm_debtors_invoices')->where('id', $locked->offset_invoice_id)->lockForUpdate()->first();
            $amount = round((float) $locked->amount, 2);
            $balance = $invoice ? $this->openBalance($invoice) : 0.0;

            if (!$invoice || !in_array((int) $invoice->current_status_id, [1, self::INVOICE_PARTIAL], true)) {
                throw new ClientAccountException('The invoice is no longer open, so this recovery cannot be confirmed.');
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

            $this->creditTenantLedger($locked, $invoice, $amount, $reason);
            $this->recordReceipt($locked, $invoice, $amount, $reason);
            $this->debitClientLedger($locked, $invoice, $amount, $userId, $reason);
            $this->postGl($locked, $invoice, $amount, $userId, $reason);

            $locked->current_status_id = self::CONFIRMED;
            $locked->actioned_by = $userId;
            $locked->actioned_at = now();
            $locked->posted_to_landlord_at = now();
            $locked->save();

            Log::info('RecoveryService: recovery confirmed', ['recovery_id' => $locked->id, 'reason' => $reason['key'], 'invoice_id' => $invoice->id, 'amount' => $amount, 'by' => $userId]);

            return ['invoice_status' => $status, 'invoice_balance' => $newBalance, 'amount' => $amount, 'receipt' => (string) $locked->ref_number];
        });
    }

    /**
     * Reject a recovery. Nothing is posted.
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
     * Credit the tenant's ledger with the recovered amount, one row like the old system (mode PO for Paid Owner, REC for
     * the other reasons), tied to the invoice. The ledger's receipt column is numeric, so the receipt (the recovery
     * reference) goes in entry_ref and on the receipts row.
     *
     * @param Recovery $recovery
     * @param object $invoice the invoice row
     * @param float $amount
     * @param array $reason the recovery's reason
     */
    protected function creditTenantLedger(Recovery $recovery, object $invoice, float $amount, array $reason): void {
        $item = InvoiceBillingService::itemName($invoice);

        DB::table('sys_pm_tenants_accounts_trans')->insert([
            'date_time' => $recovery->recovery_date ?: now(),
            'narration' => $invoice->narration ?: $item,
            'pay_for' => $item,
            'dr_cr' => 'CR',
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'receipt_number' => null,
            'payment_mode' => $reason['ledger_mode'],
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
     * Give the tenant a receipt for the recovery: one row in the receipts table the tenant's Payments tab and the Allocated
     * Payments export read, numbered with the recovery reference, in the shape the old system used (payment mode PO or REC,
     * recovery receipts flagged is_recovery). No payment row is made: no money came in, so nothing reaches the payments,
     * bank or cash reports.
     *
     * @param Recovery $recovery
     * @param object $invoice the invoice row
     * @param float $amount
     * @param array $reason the recovery's reason
     */
    protected function recordReceipt(Recovery $recovery, object $invoice, float $amount, array $reason): void {
        $item = InvoiceBillingService::itemName($invoice);
        $date = $recovery->recovery_date ? Carbon::parse($recovery->recovery_date) : now();
        $isRecoveryReceipt = $reason['ledger_mode'] === 'REC' ? 1 : 0;

        DB::table('sys_pm_tenants_payments_allocated')->insert([
            'date_time' => now(),
            'payment_date' => $date,
            'receipt_number' => mb_substr((string) $recovery->ref_number, 0, 50),
            'amount' => $amount,
            'narration' => mb_substr((string) ($invoice->narration ?: $item), 0, 255),
            'pay_for' => $item,
            'payment_month' => $date->month,
            'payment_year' => $date->year,
            'payment_mode' => $reason['ledger_mode'],
            'reference_number' => mb_substr((string) $recovery->ref_number, 0, 100),
            'entry_ref' => mb_strtoupper($reason['label']),
            'trans_amount' => 0,
            'is_recovery' => $isRecoveryReceipt,
            'is_in_account' => $isRecoveryReceipt,
            'property_id' => $invoice->property_id,
            'client_id' => $recovery->client_id,
            'unit_id' => $invoice->unit_id,
            'tenant_id' => $invoice->tenant_id,
            'zone_id' => $invoice->zone_id,
            'field_officer_id' => $invoice->field_officer_id,
            'zone_supervisor_id' => $invoice->zone_supervisor_id,
            'zone_manager_id' => $invoice->zone_manager_id,
            'current_status_id' => 1,
        ]);
    }

    /**
     * Debit the client's ledger: code 0007 (PAID OWNERS) for Paid Owner, code 0024 (Rent recovery) for the other reasons,
     * both already taken by the returns. The reason leads the particulars, so the return line reads like the old
     * ones (SAT DEPOSIT, ABSCONDED). It is left unrecovered: the next return takes it as a deduction and flags it
     * recovered when the return is finally approved.
     *
     * @param Recovery $recovery
     * @param object $invoice the invoice row
     * @param float $amount
     * @param int $userId the confirmer
     * @param array $reason the recovery's reason
     */
    protected function debitClientLedger(Recovery $recovery, object $invoice, float $amount, int $userId, array $reason): void {
        $clientId = $this->landlordOf((int) $recovery->property_id) ?: (int) $recovery->client_id;
        $codes = DB::table('sys_pm_clients as c')
            ->crossJoin('sys_pm_properties as p')
            ->where('c.id', $clientId)
            ->where('p.id', $recovery->property_id)
            ->select('c.reference_number as landlord_code', 'c.names as landlord_name', 'p.reference_number as property_code')
            ->first();

        if (!$codes) {
            throw new ClientAccountException('The client or property of recovery ' . $recovery->ref_number . ' could not be found.');
        }

        $tenant = (string) DB::table('sys_pm_clients')->where('id', $invoice->tenant_id)->value('names');
        $unit = (string) DB::table('sys_pm_units')->where('id', $invoice->unit_id)->value('unit_code');
        $particulars = mb_strtoupper($reason['label']) . ' ' . $recovery->ref_number . ' - ' . trim($tenant . ' ' . $unit . ' ' . $invoice->narration);
        $user = DB::table('josya_users')->where('id', $userId)->value('name');

        DB::table('cb_landlord_account')->insert([
            'year' => now()->year,
            'month' => now()->month,
            'landlord_code' => $codes->landlord_code,
            'landlord_name' => $codes->landlord_name,
            'property_code' => $codes->property_code,
            'transaction_number' => mb_substr((string) $recovery->ref_number, 0, 50),
            'entry_type_code' => $reason['ledger_code'],
            'entry_description' => mb_strtoupper($reason['label']),
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
     * @param array $reason the recovery's reason
     */
    protected function postGl(Recovery $recovery, object $invoice, float $amount, int $userId, array $reason): void {
        if (!$this->glEnabled()) {
            return;
        }

        $details = 'recovery_id:' . $recovery->id . ':' . $reason['key'];

        if ($this->gl->exists($details)) {
            return;
        }

        $setup = GlAccountSetup::first();
        $clients = $setup->clients_account_id ?? null;
        $tenants = $setup->tenants_account_id ?? null;

        if (!$clients || !$tenants) {
            throw new ClientAccountException('The GL setup is missing the Clients Account or the Tenants Account, so the recovery cannot be posted.');
        }

        $this->gl->journal(
            (string) $recovery->ref_number,
            $reason['label'] . ' - ' . $invoice->reference_number,
            $details,
            [
                ['account_id' => (int) $clients, 'type' => 'Debit', 'amount' => $amount],
                ['account_id' => (int) $tenants, 'type' => 'Credit', 'amount' => $amount],
            ],
            $userId,
            $reason['item_code']
        );
    }
}
