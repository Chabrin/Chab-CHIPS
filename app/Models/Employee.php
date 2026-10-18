<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'sys_staff'; // Point to the real table

    protected static function boot()
    {
        parent::boot();
        if (class_exists(AuditLogsObserver::class)) {
            static::observe(AuditLogsObserver::class);
        }
    }

    // Include the actual columns from sys_staff
    protected $fillable = [
        'full_names',
        'staff_number',
        'gender',
        'address',
        'mobile_number',
        'email_address',
        'personal_email',
        'birth_date',
        'national_id',
        'passport',
        'bank_name',
        'pin_number',
        'hire_date',
        'department_id',
        'position_id',
        'username',
        'system_password',
        'account_name',
        'account_number',
        'nhif_number',
        'nssf_number',
        'photo', // Actual photo column
    ];

      public function department()

    {

        return $this->belongsTo(Department::class, 'dept_id');

    }



    public function gender()

    {

        return $this->belongsTo(Gender::class, 'gender_id');

    }



    public function maritalStatus()

    {

        return $this->belongsTo(MaritalStatus::class, 'marital_status');

    }

    //empoyment terms relationship

    public function employmentTerms()

    {

        return $this->belongsTo(EmploymentTerm::class, 'terms');

    }

    // Documents relationship 
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    // Avatar / Profile photo helper
    public function getPhotoUrlAttribute()
    {
        if ($this->photo && Storage::disk('public')->exists($this->photo)) {
            return asset('storage/' . $this->photo);
        }

        return asset('images/default-avatar.svg');
    }
}