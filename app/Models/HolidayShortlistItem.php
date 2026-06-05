<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidayShortlistItem extends Model
{
    protected $fillable = [
        'holiday_shortlist_id',
        'scored_holiday_option_id',
        'position',
    ];

    public function shortlist(): BelongsTo
    {
        return $this->belongsTo(HolidayShortlist::class, 'holiday_shortlist_id');
    }

    public function scoredHolidayOption(): BelongsTo
    {
        return $this->belongsTo(ScoredHolidayOption::class, 'scored_holiday_option_id');
    }
}
