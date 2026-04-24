<?php

namespace App\Models;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Enums\SavedHolidaySearchRunType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedHolidaySearchRun extends Model
{
    protected $fillable = [
        'saved_holiday_search_id',
        'run_type',
        'status',
        'provider_count',
        'raw_record_count',
        'parsed_record_count',
        'normalised_record_count',
        'scored_record_count',
        'imported_holiday_option_ids',
        'started_at',
        'finished_at',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'run_type' => SavedHolidaySearchRunType::class,
            'status' => SavedHolidaySearchRunStatus::class,
            'imported_holiday_option_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(SavedHolidaySearch::class, 'saved_holiday_search_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ProviderImportSnapshot::class, 'saved_holiday_search_run_id');
    }

    public function scoredOptions(): HasMany
    {
        return $this->hasMany(ScoredHolidayOption::class, 'saved_holiday_search_run_id');
    }
}
