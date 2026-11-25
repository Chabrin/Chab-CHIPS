<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyOwnershipTransfer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['terms' => 'array', 'documents' => 'array', 'snapshot' => 'array', 'effective_date' => 'date',
            'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function fromClient()
    {
        return $this->belongsTo(Client::class, 'from_client_id')->withoutGlobalScopes();
    }

    public function toClient()
    {
        return $this->belongsTo(Client::class, 'to_client_id')->withoutGlobalScopes();
    }

    public function events()
    {
        return $this->hasMany(PropertyOwnershipTransferEvent::class, 'transfer_id')->orderBy('id');
    }

    public function getReferenceAttribute(): string
    {
        return 'POT-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
