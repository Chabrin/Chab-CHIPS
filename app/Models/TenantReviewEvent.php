<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantReviewEvent extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['snapshot' => 'encrypted:array', 'created_at' => 'datetime'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
