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
        'kids_club_age_min',
        'has_waterpark',
        'has_family_rooms',
        'play_area',
        'evening_entertainment',
        'kids_disco',
        'gym',
        'spa',
        'adults_only_area',
        'promenade',
        'near_shops',
        'distance_to_shops_meters',
        'cafes_bars',
        'distance_to_cafes_bars_meters',
        'harbour',
        'has_lift',
        'ground_floor_available',
        'accessibility_issues',
        'steps_count',
        'accessibility_notes',
        'cots_available',
        'introduction_snippet',
        'style_keywords',
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
        'images',
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
            'images' => 'array',
            'is_family_friendly' => 'boolean',
            'has_kids_club' => 'boolean',
            'kids_club_age_min' => 'integer',
            'has_waterpark' => 'boolean',
            'has_family_rooms' => 'boolean',
            'play_area' => 'boolean',
            'evening_entertainment' => 'boolean',
            'kids_disco' => 'boolean',
            'gym' => 'boolean',
            'spa' => 'boolean',
            'adults_only_area' => 'boolean',
            'promenade' => 'boolean',
            'near_shops' => 'boolean',
            'cafes_bars' => 'boolean',
            'harbour' => 'boolean',
            'has_lift' => 'boolean',
            'ground_floor_available' => 'boolean',
            'cots_available' => 'boolean',
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

    public function photos(): HasMany
    {
        return $this->hasMany(HotelPhoto::class)->orderBy('position');
    }

    /**
     * First image for UI: prefer locally cached `hotel_photos`, else first URL from structured `images` JSON.
     */
    public function primaryImageUrlForDisplay(): ?string
    {
        foreach ($this->photos as $photo) {
            $url = $photo->publicUrl();
            if ($url !== null) {
                return $url;
            }
        }

        $images = $this->images;
        if (! is_array($images) || $images === []) {
            return null;
        }
        $first = $images[0] ?? null;
        if (is_string($first) && filter_var($first, FILTER_VALIDATE_URL)) {
            return $first;
        }
        if (is_array($first) && isset($first['url']) && is_string($first['url']) && filter_var($first['url'], FILTER_VALIDATE_URL)) {
            return $first['url'];
        }

        return null;
    }
}
