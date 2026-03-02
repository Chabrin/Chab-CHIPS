<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnsBatchConfiguration extends Model
{
    protected $table = 'sys_eports_returns_batches_configurations_298_repeat';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'bank_account_id',
        // Add other fields as needed
    ];

    /**
     * Get the bank account for this batch configuration
     */
    public function bankAccount()
    {
        return $this->belongsTo(ClientBankAccount::class, 'bank_account_id');
    }

    /**
     * Get the property mappings for this batch configuration
     */
    public function propertyMappings()
    {
        return $this->hasMany(ReturnsBatchConfigPropertyMapping::class, 'parent_id');
    }

    /**
     * Get properties through the mapping table
     */
    public function properties()
    {
        return $this->hasManyThrough(
            Property::class,
            ReturnsBatchConfigPropertyMapping::class,
            'parent_id', // Foreign key on the mapping table
            'id', // Foreign key on the properties table
            'id', // Local key on this table
            'prs' // Local key on the mapping table
        );
    }
}
