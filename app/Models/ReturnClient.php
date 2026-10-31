<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ReturnClient extends Model
{
    protected $table = 'cb_returns_clients';
    public $timestamps = false;

    protected $fillable = [
        'return_code',
        'return_period',
        'transaction_number',
        'transaction_date',
        'transaction_date_num',
        'entry_description',
        'entry_type',
        'transaction_code',
        'narration',
        'transaction_ref',
        'source_date',
        'property_code',
        'unit_code',
        'unit_seq_id',
        'dr_cr',
        'tenant_name',
        'tenant_status',
        'amount_text',
        'amount_payable_landlord',
        'amount_payable_tenant',
        'residual_debit',
        'residual_credit',
        'net_amount',
        'remarks',
        'transaction_source',
        'deduction_point',
        'is_vatable',
        'is_commissionable',
        'deduction_point_2',
        'payment_period',
        'cheque_number',
        'bank_account',
        'vat_amount',
        'auth_2m',
        'auth_un',
        'pay_percentage',
        'return_line_id',
        'property_id',
        'client_id',
        'zone_id',
        'field_officer_id',
        'zone_supervisor_id',
        'zone_manager_id',
        'unit_id',
        'tenant_id',
        'return_id',
        'invoice_id',
        'payable_status',
        'payable_status_reason',
    ];

    protected $casts = [
        'payable_status' => 'integer',
        'net_amount' => 'decimal:2',
        'amount_payable_landlord' => 'decimal:2',
        'amount_payable_tenant' => 'decimal:2',
        'residual_debit' => 'decimal:2',
        'residual_credit' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'source_date' => 'datetime',
    ];

    public function summary()
    {
        return $this->belongsTo(ReturnSummary::class, 'return_code', 'return_code');
    }

    /**
     * Lines of a return in display order: grouped by entry type, unit codes in natural order within each group.
     *
     * @param string $returnCode
     * @return Collection
     */
    public static function forReturn(string $returnCode): Collection {
        return self::sortNaturally(static::where('return_code', $returnCode)->orderBy('entry_type')->orderBy('tenant_name')->get());
    }

    /**
     * Keep the entry-type groups in the order they came in and put the unit codes inside each group in
     * natural order (B2 before B10) instead of the database's text order (B10 before B2). Lines with no unit
     * go last in their group, and lines of the same unit keep the order they came in (tenant name).
     *
     * @param Collection $lines
     * @return Collection
     */
    public static function sortNaturally(Collection $lines): Collection {
        return $lines->groupBy(fn($line) => (string) $line->entry_type)->flatMap(function ($group) {
            return $group->sort(function ($a, $b) {
                $unitA = trim((string) $a->unit_code);
                $unitB = trim((string) $b->unit_code);

                if ($unitA === '' || $unitB === '') {
                    return ($unitA === '') <=> ($unitB === '');
                }

                return strnatcasecmp($unitA, $unitB);
            })->values();
        })->values();
    }
}
