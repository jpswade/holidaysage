<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidaySearchImportMapping extends Model
{
    protected $fillable = [
        'saved_holiday_search_id',
        'provider_source_id',
        'original_url',
        'extracted_criteria',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extracted_criteria' => 'array',
        ];
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(SavedHolidaySearch::class, 'saved_holiday_search_id');
    }

    public function providerSource(): BelongsTo
    {
        return $this->belongsTo(ProviderSource::class);
    }
}
