<?php

namespace App\Models;

use App\Enums\SavedHolidaySearchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SavedHolidaySearch extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'slug',
        'provider_import_url',
        'departure_airport_code',
        'departure_airport_name',
        'travel_start_date',
        'travel_end_date',
        'travel_date_flexibility_days',
        'duration_min_nights',
        'duration_max_nights',
        'adults',
        'children',
        'infants',
        'budget_total',
        'budget_per_person',
        'max_flight_minutes',
        'max_transfer_minutes',
        'board_preferences',
        'destination_preferences',
        'feature_preferences',
        'excluded_destinations',
        'excluded_features',
        'sort_preference',
        'status',
        'last_imported_at',
        'last_scored_at',
        'next_refresh_due_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SavedHolidaySearchStatus::class,
            'travel_start_date' => 'date',
            'travel_end_date' => 'date',
            'board_preferences' => 'array',
            'destination_preferences' => 'array',
            'feature_preferences' => 'array',
            'excluded_destinations' => 'array',
            'excluded_features' => 'array',
            'last_imported_at' => 'datetime',
            'last_scored_at' => 'datetime',
            'next_refresh_due_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $search): void {
            if (empty($search->uuid)) {
                $search->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SavedHolidaySearchRun::class);
    }

    public function scoredOptions(): HasMany
    {
        return $this->hasMany(ScoredHolidayOption::class);
    }

    public function importMappings(): HasMany
    {
        return $this->hasMany(HolidaySearchImportMapping::class);
    }
}
