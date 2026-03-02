<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    use HasFactory;
    protected $table = 'employees';
    protected $fillable = [
        'employee_no',
        'first_name',
        'surname',
        'other_name',
        'national_id',
        'email_address',
        'phone_no',
        'dob',
        'gender_id',
        'marital_status_id',
        'date_employed',
        'added_by',
        'department_id',
        'confirmed',
        'confirmed_by',
        'confirmed_on',
        'created_at',
        'updated_at',
        'town_id',
        'terms',
        'deleted_at',
        'is_user',
        'added_on',
        'post_address',
        'physical_address',
        'county_id',
        'country_id',
        'zip',
        'passport_photo',
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
}
