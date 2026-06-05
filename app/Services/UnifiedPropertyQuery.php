<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\ScoredHolidayOption;
use App\ViewModels\ResultCardViewModel;
use App\ViewModels\UnifiedPropertyViewModel;
use App\ViewModels\UnifiedPropertyProviderGroupViewModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves /holidays/{slug} to a unified property view across providers.
 *
 * One canonical_property_slug → many Hotel rows (potentially from Jet2 + TUI for the same hotel)
 * → packages grouped by provider, each using the same "best canonical row per package" rule
 * as global browse so users see one ranked line per package even when multiple historical
 * scored options exist for it.
 */
final class UnifiedPropertyQuery
{
    public function forSlug(string $slug): ?UnifiedPropertyViewModel
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $hotels = Hotel::query()
            ->where(function (Builder $q) use ($slug): void {
                $q->where('canonical_property_slug', $slug)
                    ->orWhere(function (Builder $orQ) use ($slug): void {
                        $orQ->whereNull('canonical_property_slug')->where('hotel_slug', $slug);
                    });
            })
            ->with(['providerSource', 'photos'])
            ->orderBy('id')
            ->get();

        if ($hotels->isEmpty()) {
            return null;
        }

        $hotelIds = $hotels->pluck('id')->all();

        $canonicalRule = 'scored_holiday_options.id = (
            select s2.id from scored_holiday_options as s2
            where s2.holiday_package_id = scored_holiday_options.holiday_package_id
            order by s2.overall_score desc, s2.id desc
            limit 1
        )';

        $options = ScoredHolidayOption::query()
            ->whereRaw($canonicalRule)
            ->whereHas('holidayPackage', function (Builder $q) use ($hotelIds): void {
                $q->whereIn('hotel_id', $hotelIds);
            })
            ->with([
                'search',
                'holidayPackage.hotel.photos',
                'holidayPackage.providerSource',
            ])
            ->orderByDesc('overall_score')
            ->orderBy('id')
            ->get();

        if ($options->isEmpty()) {
            return null;
        }

        $leadHotel = $this->chooseLeadHotel($hotels->all());

        $groups = $this->groupOptionsByProvider($options->all());

        return new UnifiedPropertyViewModel(
            canonicalPropertySlug: $leadHotel->canonicalPropertySlugOrFallback(),
            leadHotel: $leadHotel,
            providerGroups: $groups,
        );
    }

    /**
     * Lead hotel for hero / metadata: prefer richest review profile, fall back to first by id.
     *
     * @param  array<int, Hotel>  $hotels
     */
    private function chooseLeadHotel(array $hotels): Hotel
    {
        $best = $hotels[0];
        $bestKey = $this->leadKey($best);
        foreach ($hotels as $hotel) {
            $key = $this->leadKey($hotel);
            if ($key > $bestKey) {
                $best = $hotel;
                $bestKey = $key;
            }
        }

        return $best;
    }

    private function leadKey(Hotel $hotel): float
    {
        $reviewCount = is_numeric($hotel->review_count) ? (float) $hotel->review_count : 0.0;
        $reviewScore = is_numeric($hotel->review_score) ? (float) $hotel->review_score : 0.0;

        return $reviewCount * 10 + $reviewScore;
    }

    /**
     * @param  array<int, ScoredHolidayOption>  $options
     * @return list<UnifiedPropertyProviderGroupViewModel>
     */
    private function groupOptionsByProvider(array $options): array
    {
        $groups = [];
        foreach ($options as $option) {
            $package = $option->holidayPackage;
            $provider = $package?->providerSource;
            $key = $provider?->key ?? 'unknown';
            $name = $provider?->name ?? 'Unknown provider';
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'name' => $name,
                    'options' => [],
                ];
            }
            $groups[$key]['options'][] = [
                'viewModel' => ResultCardViewModel::fromModel($option),
                'option' => $option,
            ];
        }

        $result = [];
        foreach ($groups as $key => $group) {
            $result[] = new UnifiedPropertyProviderGroupViewModel(
                providerKey: $key,
                providerName: $group['name'],
                options: $group['options'],
            );
        }

        return $result;
    }
}
