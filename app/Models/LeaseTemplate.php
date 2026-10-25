<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeaseTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'template_type',
        'source_type',
        'blade_content',
        'css_styles',
        'layout_config',
        'logo_path',
        'branding_config',
        'source_pdf_path',
        'pdf_coordinate_map',
        'extraction_metadata',
        'available_variables',
        'required_variables',
        'is_active',
        'is_default',
        'version_number',
        'created_by',
        'updated_by',
        'published_at',
    ];

    protected $casts = [
        'css_styles' => 'array',
        'layout_config' => 'array',
        'pdf_coordinate_map' => 'array',
        'branding_config' => 'array',
        'extraction_metadata' => 'array',
        'available_variables' => 'array',
        'required_variables' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('template_type', $type);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Auto-generate slug
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });

    }
}
