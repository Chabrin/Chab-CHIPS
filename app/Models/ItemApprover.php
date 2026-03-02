<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemApprover extends Model
{
    protected $table = 'cb_item_approvers';

    protected $fillable = [
        'approval_item_id',
        'approver_id',
        'approver_level',
        'is_optional',
        'approval_status',
        'assigned_at',
        'actioned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get the approval item this approver belongs to.
     */
    public function approvalItem()
    {
        return $this->belongsTo(ApprovalItem::class, 'approval_item_id');
    }

    /**
     * Get the user who is the approver.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
