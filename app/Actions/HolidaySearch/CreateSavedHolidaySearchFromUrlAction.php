<?php

namespace App\Actions\HolidaySearch;

use App\Enums\SavedHolidaySearchStatus;
use App\Models\HolidaySearchImportMapping;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\Imports\ImportUrlParserRegistry;
use App\Services\Providers\ProviderSourceResolver;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CreateSavedHolidaySearchFromUrlAction
{
    public function __construct(
        private readonly ImportUrlParserRegistry $parserRegistry,
        private readonly ProviderSourceResolver $providerResolver,
    ) {}

    public function handle(string $url, ?int $userId = null): SavedHolidaySearch
    {
        $parser = $this->parserRegistry->parserFor($url);
        $extracted = $parser->parse($url);
        $provider = $this->providerResolver->forUrl($url);

        $search = SavedHolidaySearch::query()
            ->where('provider_import_url', $url)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->whereNull('user_id'))
            ->first() ?? new SavedHolidaySearch;

        $attributes = $this->mergeWithDefaults($url, $provider, $extracted, $search->exists);
        $search->forceFill($attributes);
        $search->user_id = $userId;
        $search->save();

        HolidaySearchImportMapping::query()->updateOrCreate([
            'saved_holiday_search_id' => $search->id,
            'provider_source_id' => $provider->id,
            'original_url' => $url,
        ], [
            'extracted_criteria' => $extracted,
        ]);

        return $search->fresh();
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array<string, mixed>
     */
    private function mergeWithDefaults(string $url, ProviderSource $provider, array $extracted, bool $isUpdate): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $name = 'Import — '.$provider->name.($host ? ' ('.$host.')' : '');
        $baseSlug = Str::slug('import-'.$provider->key.'-'.Str::random(8));

        $start = $extracted['travel_start_date'] ?? null;
        $end = $extracted['travel_end_date'] ?? null;
        $minNights = (int) ($extracted['duration_min_nights'] ?? 7);
        $maxNights = (int) ($extracted['duration_max_nights'] ?? $minNights);
        if ($maxNights < $minNights) {
            $maxNights = $minNights;
        }
        if ($start && ! $end) {
            $d = Carbon::parse($start);
            $nights = $maxNights > 0 ? $maxNights : 7;
            $end = $d->copy()->addDays($nights)->toDateString();
        }

        return array_filter([
            'name' => $name,
            'slug' => $isUpdate ? null : $this->uniqueSlug($baseSlug),
            'provider_import_url' => $url,
            'departure_airport_code' => $extracted['departure_airport_code'] ?? 'MAN',
            'departure_airport_name' => $extracted['departure_airport_name'] ?? null,
            'travel_start_date' => $start,
            'travel_end_date' => $end,
            'travel_date_flexibility_days' => (int) ($extracted['travel_date_flexibility_days'] ?? 0),
            'duration_min_nights' => $minNights,
            'duration_max_nights' => $maxNights,
            'adults' => (int) ($extracted['adults'] ?? 2),
            'children' => (int) ($extracted['children'] ?? 0),
            'infants' => (int) ($extracted['infants'] ?? 0),
            'board_preferences' => $extracted['board_preferences'] ?? null,
            'destination_preferences' => $extracted['destination_preferences'] ?? null,
            'feature_preferences' => $extracted['feature_preferences'] ?? null,
            'excluded_destinations' => $extracted['excluded_destinations'] ?? null,
            'excluded_features' => $extracted['excluded_features'] ?? null,
            'status' => SavedHolidaySearchStatus::Active,
        ], fn ($v) => $v !== null);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 0;
        while (SavedHolidaySearch::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
