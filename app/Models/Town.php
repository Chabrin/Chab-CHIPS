<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Town extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'towns';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'country_id',
        'county_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'country_id' => 'integer',
        'county_id' => 'integer',
    ];

    /**
     * Get the country that owns the town.
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Get the county that owns the town.
     */
    // public function county()
    // {
    //     return $this->belongsTo(County::class, 'county_id');
    // }

    /**
     * Get all members associated with this town.
     */
    public function members()
    {
        return $this->hasMany(Member::class, 'town_id');
    }
}
