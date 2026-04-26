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

    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }

    public function holidayPackages(): HasMany
    {
        return $this->hasMany(HolidayPackage::class);
    }

    public function providerDestinations(): HasMany
    {
        return $this->hasMany(ProviderDestination::class);
    }
}
