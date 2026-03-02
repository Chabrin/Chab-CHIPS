<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailTemplate extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    use HasFactory;
    protected $table = 'email_templates';

    protected $fillable = [
        'name',
        'subject',
        'body',
        'added_by',
        'template_variable',
        'comtype',
        'confirmed_by',
        'confirmed',
        'confirmed_date'
    ];

    protected $casts = [
        'template_variable' => 'array',
        'confirmed' => 'boolean',
        'confirmed_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'confirmed' => false
    ];

    // Scopes
    public function scopeConfirmed($query)
    {
        return $query->where('confirmed', true);
    }

    public function scopeUnconfirmed($query)
    {
        return $query->where('confirmed', false)->orWhereNull('confirmed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('comtype', $type);
    }

    // Accessors
    public function getIsConfirmedAttribute()
    {
        return $this->confirmed == 1;
    }

    public function getStatusAttribute()
    {
        return $this->confirmed ? 'Confirmed' : 'Pending';
    }

    public function getStatusBadgeAttribute()
    {
        return $this->confirmed
            ? '<span class="badge badge-light-success">Confirmed</span>'
            : '<span class="badge badge-light-warning">Pending</span>';
    }

    // Helper methods
    public function markAsConfirmed($confirmedBy)
    {
        $this->update([
            'confirmed' => true,
            'confirmed_by' => $confirmedBy,
            'confirmed_date' => now()
        ]);
    }

    public function markAsUnconfirmed()
    {
        $this->update([
            'confirmed' => false,
            'confirmed_by' => null,
            'confirmed_date' => null
        ]);
    }

    public function getAvailableVariables()
    {
        return $this->template_variable ?? [];
    }

    public function hasVariable($variable)
    {
        $variables = $this->getAvailableVariables();
        return in_array($variable, $variables);
    }
}
