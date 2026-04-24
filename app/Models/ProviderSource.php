<?php

namespace App\Models;

use App\Enums\ProviderSourceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderSource extends Model
{
    protected $fillable = [
        'key',
        'name',
        'base_url',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProviderSourceStatus::class,
        ];
    }

    public function importSnapshots(): HasMany
    {
        return $this->hasMany(ProviderImportSnapshot::class);
    }

    public function holidayOptions(): HasMany
    {
        return $this->hasMany(HolidayOption::class);
    }
}
