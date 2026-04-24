<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderImportSnapshot extends Model
{
    protected $fillable = [
        'saved_holiday_search_run_id',
        'provider_source_id',
        'source_url',
        'response_status',
        'snapshot_path',
        'snapshot_hash',
        'record_count_estimate',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SavedHolidaySearchRun::class, 'saved_holiday_search_run_id');
    }

    public function providerSource(): BelongsTo
    {
        return $this->belongsTo(ProviderSource::class);
    }
}
