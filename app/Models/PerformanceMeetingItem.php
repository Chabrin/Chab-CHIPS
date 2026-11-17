<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One thing discussed at a meeting: one of the four standard areas, or an extra item added for that meeting. */
class PerformanceMeetingItem extends Model
{
    protected $table = 'performance_meeting_items';

    protected $fillable = ['meeting_id', 'section', 'title', 'notes', 'approved', 'sort_order'];

    protected $casts = ['approved' => 'boolean'];

    public function meeting()
    {
        return $this->belongsTo(PerformanceMeeting::class, 'meeting_id');
    }
}
