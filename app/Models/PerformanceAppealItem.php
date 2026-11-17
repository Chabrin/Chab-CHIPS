<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One criterion the staff member disputes, with their reason. */
class PerformanceAppealItem extends Model
{
    protected $table = 'performance_appeal_items';

    protected $fillable = ['appeal_id', 'review_item_id', 'reason'];

    public function appeal()
    {
        return $this->belongsTo(PerformanceAppeal::class, 'appeal_id');
    }

    public function reviewItem()
    {
        return $this->belongsTo(PerformanceReviewItem::class, 'review_item_id');
    }
}
