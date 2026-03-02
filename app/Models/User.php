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

    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
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

    /**
     * Mutator to hash passwords automatically
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }
}
