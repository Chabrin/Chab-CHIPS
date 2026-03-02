<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EmployerLogin extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }

    protected $table = 'employer_logins';

    protected $fillable = [
        'company_id',
        'email_address',
        'names',
        'password',
        'otp_code',
        'otp_generated_at',
        'otp_expires_at',
        'otp_verified_at',
        'is_locked',
        'is_suspended',
        'role',
        'added_by',
        'added_on',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'is_suspended' => 'boolean',
        'otp_generated_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'added_on' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    // Accessors & Mutators
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_locked', false)
            ->where('is_suspended', false);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeUsers($query)
    {
        return $query->where('role', 'user');
    }

    // Helper Methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }

    public function isActive()
    {
        return !$this->is_locked && !$this->is_suspended;
    }

    public function suspend()
    {
        $this->is_suspended = true;
        $this->save();
    }

    public function unsuspend()
    {
        $this->is_suspended = false;
        $this->save();
    }

    public function lock()
    {
        $this->is_locked = true;
        $this->save();
    }

    public function unlock()
    {
        $this->is_locked = false;
        $this->save();
    }
}
