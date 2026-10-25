<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_mandatory',
    ];

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'document_type_id');
    }
}