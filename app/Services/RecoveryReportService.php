<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Recovery Register: every recovery with its reason, landlord, property, unit, tenant and invoice, and the totals by
 * reason, landlord, tenant and age. The rows and the totals are worked out here once so the page and the Excel export
 * show the same figures.
 */
class RecoveryReportService
{
    const PENDING = 1;
    const CONFIRMED = 5;
    const REJECTED = 41;

    /**
     * The age bands a recovery falls in, counted in days from its recovery date: label => [from, to or null for no limit].
     */
    const AGE_BANDS = [
        '0 to 30 days' => [0, 30],
        '31 to 60 days' => [31, 60],
        '61 to 90 days' => [61, 90],
        'Over 90 days' => [91, null],
    ];

    protected RecoveryService $recoveries;

    /**
     * @param RecoveryService $recoveries
     */
    public function __construct(RecoveryService $recoveries) {
        $this->recoveries = $recoveries;
    }

    /**
     * The recoveries that match the filters, newest first. A recovery of one of the five reasons that is Approved reads
     * Confirmed, and its receipt is its reference.
     *
     * @param array $filters date_from, date_to, property_id, type_id, status (1 Pending, 5 Confirmed or Approved, 41 Rejected), all optional
     * @param Carbon|null $today the day the age is counted to, defaults to today
     * @return Collection<int, array>
     */
    public function rows(array $filters, ?Carbon $today = null): Collection {
        $today = ($today ?: now())->copy()->startOfDay();
        $reasonIds = $this->recoveries->reasonTypeIds();

        $query = DB::table('sys_pm_recoveries as rec')
            ->leftJoin('sys_set_transactions_types as t', 't.id', '=', 'rec.type_id')
            ->leftJoin('sys_pm_clients as tn', 'tn.id', '=', 'rec.tenant_id')
            ->leftJoin('sys_pm_clients as ll', 'll.id', '=', 'rec.client_id')
            ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'rec.property_id')
            ->leftJoin('sys_pm_units as u', 'u.id', '=', 'rec.unit_id')
            ->leftJoin('sys_pm_debtors_invoices as inv', 'inv.id', '=', 'rec.offset_invoice_id')
            ->select(
                'rec.id', 'rec.ref_number', 'rec.recovery_date', 'rec.narration', 'rec.amount', 'rec.commisionable',
                'rec.returns_batch_id', 'rec.current_status_id', 'rec.type_id', 't.type_name',
                'tn.names as tenant_name', 'll.names as landlord_name', 'p.property_name', 'u.unit_code',
                'inv.reference_number as invoice_number'
            )
            ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('rec.recovery_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn ($q) => $q->whereDate('rec.recovery_date', '<=', $filters['date_to']))
            ->when(!empty($filters['property_id']), fn ($q) => $q->where('rec.property_id', $filters['property_id']))
            ->when(!empty($filters['type_id']), fn ($q) => $q->where('rec.type_id', $filters['type_id']))
            ->when(!empty($filters['status']), fn ($q) => $q->where('rec.current_status_id', $filters['status']))
            ->orderByDesc('rec.recovery_date')
            ->orderByDesc('rec.id');

        return $query->get()->values()->map(function ($r, $i) use ($today, $reasonIds) {
            $isInvoiceRecovery = in_array((int) $r->type_id, $reasonIds, true);
            $status = (int) $r->current_status_id;
            $age = $r->recovery_date ? max((int) Carbon::parse($r->recovery_date)->startOfDay()->diffInDays($today, false), 0) : null;

            return [
                'no' => $i + 1,
                'id' => (int) $r->id,
                'reference' => $r->ref_number ?? '-',
                'recovery_date' => $r->recovery_date ? Carbon::parse($r->recovery_date)->format('d/m/Y') : 'N/A',
                'reason' => $r->type_name ?? '-',
                'status' => $this->statusLabel($status, $isInvoiceRecovery),
                'landlord' => $r->landlord_name ?? 'N/A',
                'property' => $r->property_name ?? 'N/A',
                'unit' => $r->unit_code ?? '-',
                'tenant' => $r->tenant_name ?? 'N/A',
                'invoice' => $r->invoice_number ?? '-',
                'narration' => $r->narration ?? '-',
                'amount' => number_format((float) $r->amount, 2),
                'amount_raw' => round((float) $r->amount, 2),
                'commissionable' => $r->commisionable === 'Yes' ? 'Yes' : 'No',
                'receipt' => $isInvoiceRecovery && $status === self::CONFIRMED ? ($r->ref_number ?? '-') : '-',
                'returns_batch' => $r->returns_batch_id ?: '-',
                'age_days' => $age,
                'age_band' => $age === null ? 'No date' : $this->ageBand($age),
            ];
        });
    }

    /**
     * The totals of a set of rows by reason, landlord, tenant and age band, each group with its count and amount, the
     * biggest amount first (the age bands keep their order).
     *
     * @param Collection<int, array> $rows what rows() returned
     * @return array{by_reason: array, by_landlord: array, by_tenant: array, by_age: array, total: array}
     */
    public function summary(Collection $rows): array {
        $group = function (string $key, ?array $order = null) use ($rows) {
            $groups = $rows->groupBy($key)->map(fn ($items, $label) => [
                'label' => (string) $label,
                'count' => $items->count(),
                'amount' => round($items->sum('amount_raw'), 2),
            ]);

            if ($order !== null) {
                return collect($order)->map(fn ($label) => $groups->get($label, ['label' => $label, 'count' => 0, 'amount' => 0.0]))->values()->all();
            }

            return $groups->sortByDesc('amount')->values()->all();
        };

        return [
            'by_reason' => $group('reason'),
            'by_landlord' => $group('landlord'),
            'by_tenant' => $group('tenant'),
            'by_age' => $group('age_band', array_merge(array_keys(self::AGE_BANDS), ['No date'])),
            'total' => ['count' => $rows->count(), 'amount' => round($rows->sum('amount_raw'), 2)],
        ];
    }

    /**
     * The age band a number of days falls in.
     *
     * @param int $days
     */
    public function ageBand(int $days): string {
        foreach (self::AGE_BANDS as $label => [$from, $to]) {
            if ($days >= $from && ($to === null || $days <= $to)) {
                return $label;
            }
        }

        return 'Over 90 days';
    }

    /**
     * The label of a recovery's status.
     *
     * @param int $statusId
     * @param bool $isInvoiceRecovery whether it is one of the five reasons (Pending reads Pending confirmation, Approved reads Confirmed)
     */
    public function statusLabel(int $statusId, bool $isInvoiceRecovery): string {
        return match ($statusId) {
            self::PENDING => $isInvoiceRecovery ? 'Pending confirmation' : 'Pending',
            self::CONFIRMED => $isInvoiceRecovery ? 'Confirmed' : 'Approved',
            self::REJECTED => 'Rejected',
            default => 'Status ' . $statusId,
        };
    }
}
