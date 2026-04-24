<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoredHolidayOption extends Model
{
    protected $fillable = [
        'saved_holiday_search_id',
        'saved_holiday_search_run_id',
        'holiday_option_id',
        'overall_score',
        'travel_score',
        'value_score',
        'family_fit_score',
        'location_score',
        'board_score',
        'price_score',
        'is_disqualified',
        'disqualification_reasons',
        'warning_flags',
        'recommendation_summary',
        'recommendation_reasons',
        'rank_position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_disqualified' => 'boolean',
            'disqualification_reasons' => 'array',
            'warning_flags' => 'array',
            'recommendation_reasons' => 'array',
        ];
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(SavedHolidaySearch::class, 'saved_holiday_search_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SavedHolidaySearchRun::class, 'saved_holiday_search_run_id');
    }

    public function holidayOption(): BelongsTo
    {
        return $this->belongsTo(HolidayOption::class);
    }
}
