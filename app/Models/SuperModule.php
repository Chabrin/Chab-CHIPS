<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SuperModule extends Model
{
    use HasFactory;

    protected $table = 'cb_super_modules';

    protected $fillable = [
        'name',
        'icon',
        'order',
    ];

    public function modules()
    {
        return $this->hasMany(Module::class, 'super_module_id');
    }
}
