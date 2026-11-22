<?php

namespace App\Services;

use App\Exceptions\ClientAccountException;
use App\Models\Recovery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * A landlord tells us, through a link we send them, that a tenant paid them rent directly (Paid Owner), so the company
 * does not pay that rent out again. The report becomes a Pending Paid Owner recovery that the confirmer checks and confirms
 * on the Recovery page like any other, so nothing moves on the tenant's invoice or the landlord's account until a person
 * has verified it. The landlord's report has no staff creator; it says so in its narration and its origin.
 *
 * The link is signed and expires. It carries only the landlord's id, and every property, unit and invoice named in a
 * report is checked against that landlord, so a landlord can report only on their own tenants.
 */
class LandlordPaidOwnerService
{
    const LINK_DAYS = 14;
    const ORIGIN = 'LANDLORD_LINK';
    const MAX_INVOICES = 3000;

    protected RecoveryService $recoveries;

    /**
     * Inject the recovery service that checks and numbers a recovery.
     *
     * @param RecoveryService $recoveries
     */
    public function __construct(RecoveryService $recoveries) {
        $this->recoveries = $recoveries;
    }

    /**
     * The signed link a landlord opens to report a tenant who paid them directly.
     *
     * @param int $clientId
     */
    public function link(int $clientId): string {
        return URL::temporarySignedRoute('property.paid-owner-report.show', now()->addDays(self::LINK_DAYS), ['encryptedId' => Crypt::encrypt($clientId)]);
    }

    /**
     * What the landlord can report on: their properties, each with the units that have an open tenant invoice, the
     * tenant, and the open invoices (what is left on each). Only rent still owing can be reported as paid to the owner.
     *
     * @param int $clientId
     * @return array list of properties: id, name, units: id, name, tenant_id, tenant, invoices: id, number, narration, balance
     */
    public function options(int $clientId): array {
        $propertyIds = $this->propertyIds($clientId);
        if (!$propertyIds) {
            return [];
        }

        $rows = DB::table('sys_pm_debtors_invoices as i')
            ->join('sys_pm_units as u', 'u.id', '=', 'i.unit_id')
            ->join('sys_pm_properties as p', 'p.id', '=', 'i.property_id')
            ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'i.tenant_id')
            ->leftJoin('cb_tenant_records as tr', 'tr.id', '=', 'c.id')
            ->whereIn('i.property_id', $propertyIds)
            ->where('i.debtor_type_id', '2')
            ->whereIn('i.current_status_id', [1, RecoveryService::INVOICE_PARTIAL])
            ->whereRaw('COALESCE(i.current_balance, i.total_amount) > 0')
            ->whereNotNull('i.tenant_id')
            ->orderBy('p.property_name')
            ->orderBy('u.unit_name')
            ->orderByDesc('i.date_due')
            ->limit(self::MAX_INVOICES)
            ->get(['i.id', 'i.reference_number', 'i.narration', 'i.total_amount', 'i.current_balance', 'i.tenant_id', 'i.unit_id', 'i.property_id', 'p.property_name', 'u.unit_name', 'c.names as client_names', 'tr.names as record_names']);

        $properties = [];
        foreach ($rows as $row) {
            $properties[$row->property_id]['id'] = (int) $row->property_id;
            $properties[$row->property_id]['name'] = (string) $row->property_name;

            $unitKey = $row->unit_id . '-' . $row->tenant_id;
            $properties[$row->property_id]['units'][$unitKey]['id'] = (int) $row->unit_id;
            $properties[$row->property_id]['units'][$unitKey]['name'] = (string) $row->unit_name;
            $properties[$row->property_id]['units'][$unitKey]['tenant_id'] = (int) $row->tenant_id;
            $properties[$row->property_id]['units'][$unitKey]['tenant'] = (string) ($row->record_names ?: $row->client_names);
            $properties[$row->property_id]['units'][$unitKey]['invoices'][] = [
                'id' => (int) $row->id,
                'number' => (string) $row->reference_number,
                'narration' => (string) $row->narration,
                'balance' => $this->recoveries->openBalance($row),
            ];
        }

        return array_values(array_map(function ($property) {
            $property['units'] = array_values($property['units']);

            return $property;
        }, $properties));
    }

    /**
     * Record the landlord's report as a Pending Paid Owner recovery, checked against the landlord first.
     *
     * @param int $clientId the landlord the link was made for
     * @param array $data invoice_id, amount, paid_on (date), reference (their payment reference, e.g. an M-Pesa code, optional), note
     * @param UploadedFile|null $proof the PDF proof, required
     * @return Recovery
     * @throws ClientAccountException when the report does not hold up, with a message safe to show
     */
    public function report(int $clientId, array $data, ?UploadedFile $proof): Recovery {
        $invoice = DB::table('sys_pm_debtors_invoices')->where('id', (int) ($data['invoice_id'] ?? 0))->first();
        if (!$invoice) {
            throw new ClientAccountException('Choose the invoice the tenant paid you for.');
        }

        if (!in_array((int) $invoice->property_id, $this->propertyIds($clientId), true)) {
            throw new ClientAccountException('That invoice is not for one of your properties.');
        }

        $paidOn = (string) ($data['paid_on'] ?? '');
        if ($paidOn === '' || strtotime($paidOn) === false || strtotime($paidOn) > strtotime('today')) {
            throw new ClientAccountException('Give the date the tenant paid you. It cannot be in the future.');
        }

        // A Paid Owner recovery cannot be confirmed without its PDF proof, and a landlord's report has no staff creator who could
        // add one later, so the PDF is asked for here.
        if (!$proof) {
            throw new ClientAccountException('Attach a PDF of the receipt, the M-Pesa or bank statement, or your signed acknowledgement that the tenant paid you.');
        }

        $typeId = $this->paidOwnerTypeId();
        $fields = [
            'type_id' => $typeId,
            'client_id' => $clientId,
            'property_id' => (int) $invoice->property_id,
            'unit_id' => (int) $invoice->unit_id,
            'tenant_id' => (int) $invoice->tenant_id,
            'offset_invoice_id' => (int) $invoice->id,
            'amount' => round((float) ($data['amount'] ?? 0), 2),
        ];

        $this->recoveries->assertRecordable($fields);

        return DB::transaction(function () use ($fields, $invoice, $data, $paidOn, $proof, $typeId) {
            $note = trim((string) ($data['note'] ?? ''));
            $narration = 'Reported by the landlord through the Paid Owner link, tenant paid on ' . date('d/m/Y', strtotime($paidOn)) . '.' . ($note !== '' ? ' ' . $note : '');

            $recovery = Recovery::create($fields + [
                'ref_number' => $this->recoveries->nextReference(),
                'date_time' => now(),
                'recovery_date' => $paidOn,
                'narration' => mb_substr($narration, 0, 250),
                'created_by' => null,
                'origin_id' => self::ORIGIN,
                'zone_id' => DB::table('sys_pm_properties')->where('id', $invoice->property_id)->value('zone_id'),
                'sla_id' => DB::table('sys_pm_service_agreements')->where('property_id', $invoice->property_id)->orderByRaw('date_start DESC NULLS LAST')->orderByDesc('id')->value('id'),
                'commisionable' => $this->recoveries->reasonOfType($typeId)['commission'] ?? 'Yes',
                'proof_reference' => mb_substr(trim((string) ($data['reference'] ?? '')), 0, 100) ?: null,
                'current_status_id' => RecoveryService::PENDING,
            ]);

            if ($proof) {
                $this->recoveries->attachProof($recovery, $proof, 0);
            }

            return $recovery;
        });
    }

    /**
     * The ids of the landlord's properties: the ones that name them as client, and the ones whose latest service agreement does.
     *
     * @param int $clientId
     * @return int[]
     */
    public function propertyIds(int $clientId): array {
        return DB::table('sys_pm_properties as p')
            ->where(function ($q) use ($clientId) {
                $q->where('p.client_id', $clientId)->orWhereExists(function ($sub) use ($clientId) {
                    $sub->select(DB::raw(1))->from('sys_pm_service_agreements as s')->whereColumn('s.property_id', 'p.id')->where('s.client_d', $clientId);
                });
            })
            ->pluck('p.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The id of the Paid Owner transaction type.
     *
     * @throws ClientAccountException when the type is missing
     */
    protected function paidOwnerTypeId(): int {
        foreach ($this->recoveries->reasonTypes() as $typeId => $reason) {
            if ($reason['item_code'] === RecoveryService::PAID_OWNER_CODE) {
                return (int) $typeId;
            }
        }

        throw new ClientAccountException('Paid Owner is not set up. Please contact us.');
    }
}
