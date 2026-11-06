<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAdvance extends Model
{
    protected $table = 'sys_pm_clients_advances_requisitions';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'requisition_date',
        'narration',
        'created_by',
        'approved_by',
        'approved_at',
        'zone_id',
        'client_id',
        'property_id',
        'current_status_id',
        'amount',
        'repayment_months',
        'interest_rate',
        'principal_start_date',
        'disbursement_date',
        'interest_amount',
        'total_amount',
        'reference_number',
        'advance_type_id',
        'previous_advance_id',
        'top_up',
        'miscallenous_charge_id',
        'charge_amount',
        'current_balance',
        'one_off',
        'voucher_id',
        'loan_form_path',
        'loan_form_name',
        'loan_form_uploaded_by',
        'loan_form_uploaded_at',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'requisition_date' => 'datetime',
        'principal_start_date' => 'datetime',
        'disbursement_date' => 'datetime',
        'approved_at' => 'datetime',
        'loan_form_uploaded_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function invoiceableitems()
    {
        return $this->belongsTo(InvoiceableItem::class, 'miscallenous_charge_id', 'id');
    }

    public function schedule()
    {
        return $this->hasMany(AdvanceSchedule::class, 'parent_id')->orderBy('payment_date', 'asc');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'current_status_id');
    }

    /**
     * The advance that this one was created to replace (the predecessor in the chain).
     */
    public function previousAdvance()
    {
        return $this->belongsTo(ClientAdvance::class, 'previous_advance_id');
    }

    /**
     * The advance that replaced this one (successor in the chain).
     */
    public function nextReschedule()
    {
        return $this->hasOne(ClientAdvance::class, 'previous_advance_id');
    }

    /**
     * History entries where this advance was the originating (replaced) advance.
     */
    public function rescheduleHistory()
    {
        return $this->hasMany(AdvanceRescheduleHistory::class, 'from_advance_id');
    }

    public function pendingSchedules()
    {
        return $this->hasMany(AdvanceSchedule::class, 'parent_id')
            ->where('current_status_id', 1)
            ->orderBy('payment_date', 'asc');
    }

    public function clearedSchedules()
    {
        return $this->hasMany(AdvanceSchedule::class, 'parent_id')
            ->where('current_status_id', 2)
            ->orderBy('payment_date', 'asc');
    }

    /**
     * The real landlord ledger entries posted for this advance (cb_landlord_account),
     * as opposed to the internal repayment plan in AdvanceSchedule.
     */
    public function landlordAccountEntries()
    {
        return $this->hasMany(LandlordAccount::class, 'advance_id')->orderBy('entry_date', 'asc');
    }

    /**
     * True once any linked ledger entry has been marked recovered.
     */
    public function hasRecoveredLandlordEntries(): bool
    {
        return $this->landlordAccountEntries()->where('is_recovered', 1)->exists();
    }

    /**
     * IDs of AdvanceSchedule (sys_pm_clients_advances_requisitions_89_repeat) rows that
     * have actually been recovered, per cb_landlord_account.is_recovered — joined via
     * schedule_id. This is the source of truth, NOT AdvanceSchedule.current_status_id:
     * the returns-processing flow for merge-batch/MASTERRETURN landlords flips
     * is_recovered on the ledger row but never syncs it back to current_status_id, so an
     * installment can be genuinely recovered while its schedule row still sits at
     * current_status_id = 1 (Pending) indefinitely.
     */
    public function recoveredScheduleIds()
    {
        return $this->landlordAccountEntries()
            ->where('is_recovered', 1)
            ->whereNotNull('schedule_id')
            ->pluck('schedule_id');
    }

    /**
     * Principal still outstanding across installments that have NOT actually been
     * recovered in the landlord ledger — the floor that a restructure of an Overpayment
     * Advance is never allowed to go below.
     */
    public function remainingPendingPrincipal(): float
    {
        return (float) $this->schedule()
            ->whereNotIn('id', $this->recoveredScheduleIds())
            ->sum('principal_amount');
    }

    /**
     * Whether there's anything left to restructure. Overpayment Advances (installments)
     * can be restructured as long as at least one installment hasn't actually been
     * recovered yet (per the ledger) — already-recovered installments are left untouched.
     * A one-off Client Payment Advance has no partial concept, so it's all-or-nothing.
     */
    public function canBeRestructured(): bool
    {
        if ($this->advance_type_id == 3) {
            return $this->schedule()
                ->whereNotIn('id', $this->recoveredScheduleIds())
                ->exists();
        }
        return !$this->hasRecoveredLandlordEntries();
    }

    public function getTotalPaidAttribute()
    {
        return $this->schedule()
            ->where('current_status_id', 2)
            ->sum(\DB::raw('CAST(principal_amount AS numeric) + COALESCE(CAST(interest AS numeric), 0)'));
    }

    public function getPercentagePaidAttribute()
    {
        if ((float) $this->total_amount == 0) {
            return 0;
        }
        $totalPaid = $this->schedule()
            ->where('current_status_id', 2)
            ->sum(\DB::raw('CAST(principal_amount AS numeric) + COALESCE(CAST(interest AS numeric), 0)'));
        return round(($totalPaid / (float) $this->total_amount) * 100, 2);
    }
}
