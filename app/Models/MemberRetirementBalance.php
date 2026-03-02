<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberRetirementBalance extends Model
{
    use HasFactory;

    protected $table = 'member_retirement_balances';

    protected $fillable = [
        'member_no',
        'account_no',
        'employee_amount',
        'employee_units',
        'employee_registered_amount',
        'employee_unregistered_amount',
        'employee_registered_units',
        'employee_unregistered_units',
        'employer_amount',
        'employer_units',
        'employer_registered_amount',
        'employer_unregistered_amount',
        'employer_registered_units',
        'employer_unregistered_units',
        'server_time',
        'added_by',
        'added_on',
        'approved',
        'approved_by',
        'approved_on'
    ];

    protected $casts = [
        'employee_amount' => 'decimal:2',
        'employee_units' => 'decimal:6',
        'employee_registered_amount' => 'decimal:2',
        'employee_unregistered_amount' => 'decimal:2',
        'employee_registered_units' => 'decimal:6',
        'employee_unregistered_units' => 'decimal:6',
        'employer_amount' => 'decimal:2',
        'employer_units' => 'decimal:6',
        'employer_registered_amount' => 'decimal:2',
        'employer_unregistered_amount' => 'decimal:2',
        'employer_registered_units' => 'decimal:6',
        'employer_unregistered_units' => 'decimal:6',
        'server_time' => 'datetime',
        'added_on' => 'datetime',
        'approved' => 'boolean',
        'approved_on' => 'datetime'
    ];

    /**
     * Get the user who added this record
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Get the user who approved this record
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the member associated with this balance
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_no', 'member_no');
    }

    /**
     * Scope for approved balances
     */
    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    /**
     * Scope for pending approval balances
     */
    public function scopePending($query)
    {
        return $query->where('approved', false);
    }

    /**
     * Scope for specific member
     */
    public function scopeForMember($query, $memberNo)
    {
        return $query->where('member_no', $memberNo);
    }

    /**
     * Get total employee amount
     */
    public function getTotalEmployeeAmountAttribute()
    {
        return $this->employee_registered_amount + $this->employee_unregistered_amount;
    }

    /**
     * Get total employer amount
     */
    public function getTotalEmployerAmountAttribute()
    {
        return $this->employer_registered_amount + $this->employer_unregistered_amount;
    }

    /**
     * Get grand total amount
     */
    public function getGrandTotalAmountAttribute()
    {
        return $this->total_employee_amount + $this->total_employer_amount;
    }
}
