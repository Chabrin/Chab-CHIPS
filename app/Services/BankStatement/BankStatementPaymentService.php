<?php

namespace App\Services\BankStatement;

use App\Exceptions\BankStatementException;
use App\Jobs\SendPaymentReceiptJob;
use App\Models\LeaseAgreement;
use App\Services\PaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pays the matched lines of a confirmed statement in. Each Matched line becomes a tenant payment and goes straight
 * through the existing allocation (oldest month first, bills before rent), which writes the allocation rows, the invoice
 * balances, the tenant-ledger credit per invoice and the GL journal. One line is one database transaction: the payment,
 * its allocation and the line's new status are saved together or not at all, so a line that fails leaves nothing behind
 * and can be run again. The receipt is queued after the line is safely saved. The client ledger is not touched here:
 * it moves at the monthly return, as before.
 */
class BankStatementPaymentService
{
    protected PaymentAllocationService $allocation;

    /**
     * @param PaymentAllocationService $allocation
     */
    public function __construct(PaymentAllocationService $allocation) {
        $this->allocation = $allocation;
    }

    /**
     * Pay in every Matched line of one upload that has no payment yet and record the totals on the upload. Runs for an
     * upload that is Matched, or Failed when it is started again. A line that cannot be paid keeps its Matched status
     * with the reason in its note, and the upload ends Failed so the run can be repeated for those lines only.
     *
     * @param int $uploadId
     */
    public function payUpload(int $uploadId): void {
        $upload = DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->first();

        if (!$upload || !in_array($upload->status, ['Matched', 'Failed'], true)) {
            return;
        }

        $definition = BankStatementBanks::get($upload->bank);
        $table = $definition['table'];
        $errors = 0;

        DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->update(['status' => 'Paying', 'error' => null, 'updated_at' => now()]);

        DB::table($table)
            ->where('upload_id', $uploadId)
            ->where('match_status', 'Matched')
            ->whereNull('payment_id')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($upload, $definition, $table, &$errors) {
                foreach ($chunk as $line) {
                    try {
                        $paymentId = $this->payLine($upload, $definition, (int) $line->id);

                        // The receipt is queued only after the line's transaction has committed
                        if ($paymentId) {
                            SendPaymentReceiptJob::dispatch($upload->bank, (int) $line->id, $paymentId, (int) $upload->confirmed_by);
                        }
                    } catch (Throwable $e) {
                        $errors++;
                        Log::error('[BankStatement] Line could not be paid', ['upload_id' => $upload->id, 'line_id' => $line->id, 'error' => $e->getMessage()]);
                        DB::table($table)->where('id', $line->id)->update(['match_note' => mb_substr('Payment failed: ' . $e->getMessage(), 0, 255), 'updated_at' => now()]);
                    }
                }
            });

        $paid = DB::table($table)->where('upload_id', $uploadId)->where('match_status', 'Paid');

        DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->update([
            'status' => $errors > 0 ? 'Failed' : 'Completed',
            'error' => $errors > 0 ? $errors . ' line(s) could not be paid. Their reasons are in the line notes; use Run again to try them again.' : null,
            'paid_count' => (clone $paid)->count(),
            'paid_amount' => round((float) (clone $paid)->sum($definition['amount']), 2),
            'updated_at' => now(),
        ]);
    }

    /**
     * Pay one Matched line in. Returns the new payment's id, or null when there was nothing to do (the line was already
     * paid or is no longer Matched) or the transaction turned out to be a duplicate. Throws when the payment or its
     * allocation cannot be saved; nothing is kept then.
     *
     * @param object $upload the upload row
     * @param array $definition the bank's entry from BankStatementBanks
     * @param int $lineId
     * @param int|null $userId who is paying the line in when it is not the statement's confirmer (a line matched by hand)
     */
    public function payLine(object $upload, array $definition, int $lineId, ?int $userId = null): ?int {
        return DB::transaction(function () use ($upload, $definition, $lineId, $userId) {
            $table = $definition['table'];
            $line = DB::table($table)->where('id', $lineId)->lockForUpdate()->first();

            if (!$line || $line->match_status !== 'Matched' || $line->payment_id) {
                return null;
            }

            $reference = $line->{$definition['ref']};
            $amount = round((float) $line->{$definition['amount']}, 2);

            // Checked again inside the transaction: another run or the payments screen may have recorded it since matching
            // A reference that is a unit code repeats every month, so for those the date has to match too
            $recorded = DB::table('sys_pm_tenants_payments')->where('reference', $reference)->where('amount', $amount)
                ->when(StatementParser::isUnitCodeCell($reference), fn ($query) => $query->whereDate('payment_date', $line->{$definition['date']}));

            if ($recorded->exists()) {
                DB::table($table)->where('id', $lineId)->update([
                    'match_status' => 'Duplicate',
                    'match_note' => 'This transaction was recorded as a payment while the statement was waiting.',
                    'updated_at' => now(),
                ]);

                return null;
            }

            $context = $this->context((int) $line->lease_id);
            $receiptNumber = $this->nextReceiptNumber();
            $bankLabel = $definition['label'];

            $paymentId = DB::table('sys_pm_tenants_payments')->insertGetId([
                'date_time' => now(),
                'payment_date' => $line->{$definition['date']},
                'reference' => $reference,
                'narration' => mb_substr(strtoupper($bankLabel) . ' STATEMENT ' . $line->unit_code, 0, 255),
                'created_by' => $userId ?? $upload->confirmed_by,
                'current_status_id' => 11,
                'amount' => $amount,
                'trans_details' => mb_substr($bankLabel . ' statement #' . $upload->id . ' row ' . $line->row_no, 0, 255),
                'receipt_number' => $receiptNumber,
                'extract_id' => $upload->id,
            ] + $context);

            $this->allocation->allocatePaymentById($paymentId);

            DB::table($table)->where('id', $lineId)->update([
                'match_status' => 'Paid',
                'match_note' => null,
                'payment_id' => $paymentId,
                'receipt_status' => 'Queued',
                'updated_at' => now(),
            ]);

            return $paymentId;
        });
    }

    /**
     * The tenant, unit, property, client, zone, officers and service agreement a payment carries, taken from its lease
     * and property. The service agreement is the one the lease resolves to (its own, else the property's latest), the
     * same rule invoices use, because most leases have no sla_id of their own and the commission depends on it.
     *
     * @param int $leaseId
     * @return array<string, int|null>
     */
    private function context(int $leaseId): array {
        $lease = DB::table('sys_pm_lease_agreements')->where('id', $leaseId)->first(['id', 'client_id', 'property_id', 'unit_id', 'tenant_id', 'zone_id']);

        if (!$lease) {
            throw new BankStatementException('Lease ' . $leaseId . ' no longer exists.');
        }

        $property = DB::table('sys_pm_properties')->where('id', $lease->property_id)->lockForUpdate()->first(['client_id', 'zone_id', 'field_officer_id', 'zone_supervisor_id', 'zone_manager_id']);
        // A handover may have changed the operational lease links while the property lock was waiting.
        $lease = DB::table('sys_pm_lease_agreements')->where('id', $leaseId)->first(['id', 'client_id', 'property_id', 'unit_id', 'tenant_id', 'zone_id']);

        return [
            'client_id' => $lease->client_id ?: ($property->client_id ?? null),
            'property_id' => $lease->property_id,
            'unit_id' => $lease->unit_id,
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'zone_id' => $lease->zone_id ?: ($property->zone_id ?? null),
            'field_officer_id' => $property->field_officer_id ?? null,
            'zone_supervisor_id' => $property->zone_supervisor_id ?? null,
            'zone_manager_id' => $property->zone_manager_id ?? null,
            'sla_id' => LeaseAgreement::find($leaseId)?->resolvedServiceAgreement()?->id,
        ];
    }

    /**
     * The next receipt number, RCT-year-number, for example RCT-2026-00001. A counter row per year is locked while the
     * number is taken, so two payments never get the same one. It must run inside the line's transaction, which keeps
     * the lock until the payment holding the number is saved.
     */
    private function nextReceiptNumber(): string {
        $year = (int) now()->format('Y');

        DB::table('cb_receipt_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $last = (int) DB::table('cb_receipt_counters')->where('year', $year)->lockForUpdate()->value('last_number');
        DB::table('cb_receipt_counters')->where('year', $year)->update(['last_number' => $last + 1]);

        return sprintf('RCT-%d-%05d', $year, $last + 1);
    }
}
