<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HolidayOption extends Model
{
    protected $fillable = [
        'provider_source_id',
        'provider_option_id',
        'provider_hotel_id',
        'provider_url',
        'hotel_name',
        'hotel_slug',
        'resort_name',
        'destination_name',
        'destination_country',
        'airport_code',
        'departure_date',
        'return_date',
        'nights',
        'adults',
        'children',
        'infants',
        'board_type',
        'price_total',
        'price_per_person',
        'currency',
        'flight_outbound_duration_minutes',
        'flight_inbound_duration_minutes',
        'transfer_minutes',
        'distance_to_beach_meters',
        'distance_to_centre_meters',
        'star_rating',
        'review_score',
        'review_count',
        'is_family_friendly',
        'has_kids_club',
        'has_waterpark',
        'has_family_rooms',
        'latitude',
        'longitude',
        'raw_attributes',
        'signature_hash',
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'raw_attributes' => 'array',
            'is_family_friendly' => 'boolean',
            'has_kids_club' => 'boolean',
            'has_waterpark' => 'boolean',
            'has_family_rooms' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function providerSource(): BelongsTo
    {
        return $this->belongsTo(ProviderSource::class);
    }

    public function scoredOptions(): HasMany
    {
        return $this->hasMany(ScoredHolidayOption::class);
    }
}
