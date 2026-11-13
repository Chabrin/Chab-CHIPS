<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A way people are employed (Permanent & Pensionable, Contract, Casual...). HR manages the list under HR Settings.
 * Staff store the category's "code" in sys_staff.employment_type, so renaming or switching one off never changes
 * what is stored: a switched-off category is only left out of the choices for new staff and still shows on everyone in it.
 */
class EmploymentCategory extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'employment_categories';

    protected $fillable = ['code', 'name', 'active', 'staff_number_prefix', 'gets_login', 'has_end_date', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
        'gets_login' => 'boolean',
        'has_end_date' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * How many staff are in this category, whatever their status: they are what stops it being deleted.
     */
    public function staffCount(): int
    {
        return Staff::where('employment_type', $this->code)->count();
    }

    /**
     * The codes that can be chosen for a new staff member.
     */
    public static function activeCodes(): array
    {
        return static::active()->pluck('code')->all();
    }

    /**
     * Categories whose staff do not get a system login (casual workers).
     */
    public static function codesWithoutLogin(): array
    {
        return static::where('gets_login', false)->pluck('code')->all();
    }

    /**
     * Categories whose staff have no contract end date (permanent staff have a probation period instead).
     */
    public static function codesWithoutEndDate(): array
    {
        return static::where('has_end_date', false)->pluck('code')->all();
    }

    /**
     * The staff number prefix for a stored code ("CT" for Contract). Empty for plain numbers, and for a code with no category.
     */
    public static function prefixFor(?string $code): string
    {
        return (string) (static::where('code', (string) $code)->value('staff_number_prefix') ?? '');
    }

    /**
     * The name to show for a stored code, switched off or not. A code with no category (older data) is shown as it is.
     */
    public static function nameFor(?string $code): string
    {
        if ($code === null || $code === '') {
            return '-';
        }

        return static::where('code', $code)->value('name') ?? $code;
    }

    /**
     * A new code for a name: letters and digits only, and not one that is already taken ("Fixed Term" -> "FixedTerm").
     */
    public static function codeFor(string $name): string
    {
        $base = substr(preg_replace('/[^A-Za-z0-9]/', '', Str::studly($name)) ?: 'Category', 0, 40);
        $code = $base;
        for ($suffix = 2; static::where('code', $code)->exists(); $suffix++) {
            $code = $base . $suffix;
        }

        return $code;
    }
}
