<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A provider-scoped catalogue row for a sellable "area" / region id from that provider’s APIs or URLs
 * (e.g. Jet2 `destination` / `destinationAreaIds` tokens, TUI resort ids when we add them).
 * Names and meta are filled as we learn them; ids are registered from import URLs first.
 */
class ProviderDestination extends Model
{
    protected $fillable = [
        'provider_source_id',
        'area_id',
        'name',
        'slug',
        'country_code',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function providerSource(): BelongsTo
    {
        return $this->belongsTo(ProviderSource::class);
    }

    /**
     * @param  list<string>  $areaIdStrings
     */
    public static function registerProviderIdsWithoutNames(ProviderSource $provider, array $areaIdStrings): void
    {
        if ($areaIdStrings === []) {
            return;
        }
        foreach ($areaIdStrings as $id) {
            if (! is_string($id) || $id === '' || ! ctype_digit($id)) {
                continue;
            }
            self::query()->updateOrCreate(
                [
                    'provider_source_id' => $provider->id,
                    'area_id' => $id,
                ],
                [
                    'name' => null,
                    'slug' => null,
                    'country_code' => null,
                    'meta' => null,
                ]
            );
        }
    }
}
