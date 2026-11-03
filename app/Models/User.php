<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\AuditLogsObserver;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laratrust\Traits\HasRolesAndPermissions;
use Laratrust\Contracts\LaratrustUser;

class User extends Authenticatable implements LaratrustUser
{
    use HasFactory, Notifiable, HasRolesAndPermissions;

    protected ?string $pendingPassword = null;

    public function save(array $options = [])
    {
        if (!$this->isDirty('password')) {
            return parent::save($options);
        }

        $saved = $this->getConnection()->transaction(function () use ($options) {
            $previousHash = $this->exists
                ? $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->value('password')
                : null;
            $policy = app(\App\Services\StaffPasswordPolicy::class);
            if ($this->pendingPassword !== null) {
                $policy->validate($this, $this->pendingPassword, $previousHash);
            }
            $saved = parent::save($options);
            if ($saved) {
                $policy->record($this, $previousHash);
            }
            return $saved;
        });
        $this->pendingPassword = null;

        return $saved;
    }

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);

        // A password change or block revokes any "Remember me" cookie already issued
        static::saving(function ($user) {
            if ($user->isDirty('password') || ($user->isDirty('block') && $user->block)) {
                $user->remember_token = null;
            }
        });
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'josya_users';
    public $timestamps = false;

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'block',
        'sendEmail',
        'registerDate',
        'lastvisitDate',
        'activation',
        'params',
        'lastResetTime',
        'resetCount',
        'otpKey',
        'otep',
        'requireReset',
        'reset_token',
        'reset_token_expires_at',
        'staff_id',
        'is_field_officer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'otpKey',
        'otep',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'block' => 'boolean',
        'sendEmail' => 'boolean',
        'registerDate' => 'datetime',
        'lastvisitDate' => 'datetime',
        'lastResetTime' => 'datetime',
        'resetCount' => 'integer',
        'requireReset' => 'boolean',
        'params' => 'array',
        'reset_token_expires_at' => 'datetime',
    ];

    /**
     * Example relationship: if linked to an Employee table
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    /**
     * User access logs relationships
     */
    public function accessLogs()
    {
        return $this->hasMany(UserAccessLog::class);
    }

    public function recentAccessLogs($days = 30)
    {
        return $this->hasMany(UserAccessLog::class)->recent($days);
    }

    public function loginLogs()
    {
        return $this->hasMany(UserAccessLog::class)->action('login');
    }

    public function logoutLogs()
    {
        return $this->hasMany(UserAccessLog::class)->action('logout');
    }

    public function staffLink()
    {
        return $this->hasOne(UserStaffLink::class);
    }

    /**
     * The staff record linked to this system user: the link table first, then the legacy staff_id column.
     */
    public function linkedStaff(): ?Staff {
        return $this->staffLink?->staff ?? ($this->staff_id ? Staff::find($this->staff_id) : null);
    }

    /**
     * Mutator to hash passwords automatically
     */
    public function setPasswordAttribute($value)
    {
        $this->pendingPassword = $value;
        $this->attributes['password'] = bcrypt($value);
    }
}
