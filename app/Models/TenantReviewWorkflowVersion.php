<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantReviewWorkflowVersion extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['steps' => 'array', 'created_at' => 'datetime'];

    public function author()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
