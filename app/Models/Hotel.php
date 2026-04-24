<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    protected $fillable = [
        'provider_source_id',
        'provider_hotel_id',
        'hotel_identity_hash',
        'hotel_name',
        'hotel_slug',
        'resort_name',
        'destination_name',
        'destination_country',
        'star_rating',
        'review_score',
        'review_count',
        'is_family_friendly',
        'has_kids_club',
        'has_waterpark',
        'has_family_rooms',
        'has_lift',
        'ground_floor_available',
        'accessibility_issues',
        'distance_to_beach_meters',
        'distance_to_centre_meters',
        'rooms_count',
        'blocks_count',
        'floors_count',
        'restaurants_count',
        'bars_count',
        'pools_count',
        'sports_leisure_count',
        'distance_to_airport_km',
        'latitude',
        'longitude',
        'raw_attributes',
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_attributes' => 'array',
            'is_family_friendly' => 'boolean',
            'has_kids_club' => 'boolean',
            'has_waterpark' => 'boolean',
            'has_family_rooms' => 'boolean',
            'has_lift' => 'boolean',
            'ground_floor_available' => 'boolean',
            'distance_to_airport_km' => 'decimal:2',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function providerSource(): BelongsTo
    {
        return $this->belongsTo(ProviderSource::class);
    }

    public function holidayPackages(): HasMany
    {
        return $this->hasMany(HolidayPackage::class);
    }
}
