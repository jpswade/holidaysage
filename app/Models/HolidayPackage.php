<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HolidayPackage extends Model
{
    protected $fillable = [
        'provider_source_id',
        'hotel_id',
        'provider_option_id',
        'provider_url',
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
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function providerSource(): BelongsTo
    {
        return $this->belongsTo(ProviderSource::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function scoredOptions(): HasMany
    {
        return $this->hasMany(ScoredHolidayOption::class);
    }
}
