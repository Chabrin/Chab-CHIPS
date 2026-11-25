<?php

namespace App\Services;

use App\Models\PropertyOwnershipTransfer;
use App\Models\ServiceAgreement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ownership by accounting period; a future transfer never becomes the current owner early. */
class PropertyOwnershipResolver
{
    public function agreement(int $propertyId, string $asOf): ?ServiceAgreement
    {
        if (! Schema::hasTable('property_ownership_transfers')) {
            return null;
        }
        $previous = PropertyOwnershipTransfer::where('property_id', $propertyId)->where('status', 'completed')
            ->whereDate('effective_date', '<=', $asOf)->orderByDesc('effective_date')->first();
        if ($previous) {
            return ServiceAgreement::findOrFail($previous->new_sla_id);
        }
        $next = PropertyOwnershipTransfer::where('property_id', $propertyId)->where('status', 'completed')->orderBy('effective_date')->first();

        if (! $next) {
            return null;
        }
        $sla = ServiceAgreement::findOrFail($next->old_sla_id);
        if (! $sla->client_d) {
            $sla->client_d = $next->from_client_id;
        }

        return $sla;
    }

    public function invoiceClient(object $invoice, ?string $asOf = null): ?int
    {
        if (! Schema::hasTable('property_ownership_transfers')) {
            return null;
        }
        if (Schema::hasTable('property_transfer_invoice_assignments')) {
            $assigned = DB::table('property_transfer_invoice_assignments')->where('invoice_id', $invoice->id)
                ->whereDate('effective_date', '<=', $asOf ?? today()->toDateString())->orderByDesc('effective_date')->orderByDesc('id')->value('client_id');
            if ($assigned) {
                return (int) $assigned;
            }
        }
        $agreement = $this->agreement((int) $invoice->property_id, substr((string) ($invoice->returns_period ?? $invoice->date_due ?? today()->toDateString()), 0, 10));

        return $agreement ? (int) $agreement->client_d : null;
    }

    /** Fixed internal SQL aliases only; the return month has already been validated by the caller. */
    public static function returnAgreementSql(string $month): string
    {
        return "COALESCE(
            (SELECT a.sla_id FROM property_transfer_invoice_assignments a
             WHERE a.invoice_id = i.id AND a.effective_date <= {$month}
             ORDER BY a.effective_date DESC, a.id DESC LIMIT 1),
            (SELECT ot.new_sla_id FROM property_ownership_transfers ot
             WHERE ot.property_id = i.property_id AND ot.status = 'completed' AND ot.effective_date <= i.returns_period::date
             ORDER BY ot.effective_date DESC, ot.id DESC LIMIT 1),
            (SELECT ot.old_sla_id FROM property_ownership_transfers ot
             WHERE ot.property_id = i.property_id AND ot.status = 'completed' AND ot.effective_date > i.returns_period::date
             ORDER BY ot.effective_date, ot.id LIMIT 1),
            (SELECT fallback.id FROM sys_pm_service_agreements fallback WHERE fallback.property_id = i.property_id
             ORDER BY fallback.date_start DESC NULLS LAST, fallback.id DESC LIMIT 1)
        )";
    }
}
