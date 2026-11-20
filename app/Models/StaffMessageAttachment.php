<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMessageAttachment extends Model
{
    protected $table = 'cb_staff_message_attachments';

    protected $fillable = ['original_name', 'storage_path', 'mime_type', 'size_bytes', 'checksum'];

    protected $casts = ['size_bytes' => 'integer'];
}
