<?php

namespace App\Services;

use App\Models\ScoredHolidayOption;
use App\ViewModels\ResultCardViewModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Resolves the global /holidays browse list from scored imports (one best row per holiday package).
 */
final class BrowseHolidaysQuery
{
    public const int LIMIT = 200;

    /**
     * @return list<array{row: \App\Models\ScoredHolidayOption, viewModel: ResultCardViewModel}>
     */
    public function filteredCards(Request $request): array
    {
        $provider = strtolower((string) $request->query('provider', 'all'));
        if (! in_array($provider, ['all', 'jet2', 'tui'], true)) {
            $provider = 'all';
        }
        $board = (string) $request->query('board', 'all');
        if (! in_array($board, ['all', 'all_inclusive', 'half_board', 'bed_breakfast', 'self_catering', 'other'], true)) {
            $board = 'all';
        }
        $q = trim((string) $request->query('q', ''));

        $query = $this->baseQuery()
            ->with([
                'search',
                'holidayPackage.hotel',
                'holidayPackage.providerSource',
            ]);

        if ($provider !== 'all') {
            $query->whereHas('holidayPackage.providerSource', function (Builder $b) use ($provider): void {
                $b->where('key', $provider);
            });
        }

        if ($board !== 'all') {
            $query->whereHas('holidayPackage', function (Builder $b) use ($board): void {
                self::applyBoardFilter($b, $board);
            });
        }

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function (Builder $outer) use ($like): void {
                $outer->whereHas('holidayPackage', function (Builder $packageQuery) use ($like): void {
                    $packageQuery->whereHas('hotel', function (Builder $hotelQuery) use ($like): void {
                        $hotelQuery->where('hotel_name', 'like', $like)
                            ->orWhere('resort_name', 'like', $like)
                            ->orWhere('destination_name', 'like', $like);
                    });
                });
            });
        }

        $query->orderByDesc('scored_holiday_options.overall_score')
            ->orderByDesc('scored_holiday_options.id')
            ->limit(self::LIMIT);

        $out = [];
        foreach ($query->get() as $row) {
            $out[] = [
                'row' => $row,
                'viewModel' => ResultCardViewModel::fromModel($row),
            ];
        }

        return $out;
    }

    public function totalUnfilteredCount(): int
    {
        return (int) $this->baseQuery()->count();
    }

    private function baseQuery(): Builder
    {
        $correlated = 'scored_holiday_options.id = (
            select s2.id from scored_holiday_options as s2
            where s2.holiday_package_id = scored_holiday_options.holiday_package_id
            and s2.is_disqualified = 0
            order by s2.overall_score desc, s2.id desc
            limit 1
        )';

        return ScoredHolidayOption::query()
            ->where('is_disqualified', false)
            ->whereHas('holidayPackage', function (Builder $q): void {
                $q->whereNotNull('hotel_id');
            })
            ->whereRaw($correlated);
    }

    private static function applyBoardFilter(Builder $package, string $board): void
    {
        $bt = 'board_type';
        $br = 'board_recommended';

        match ($board) {
            'all_inclusive' => $package->where(function (Builder $p) use ($bt, $br): void {
                $p->whereIn($bt, ['5', 'AI', 'all_inclusive', 'All Inclusive', 'all-inclusive', 'all inclusive'])
                    ->orWhere($br, 'like', '%All Inclusive%')
                    ->orWhere($br, 'like', '%all inclusive%');
            }),
            'half_board' => $package->where(function (Builder $p) use ($bt, $br): void {
                $p->whereIn($bt, ['3', 'HB', 'half_board', 'Half Board', 'half-board', 'half board'])
                    ->orWhere($br, 'like', '%Half Board%')
                    ->orWhere($br, 'like', '%half board%');
            }),
            'bed_breakfast' => $package->where(function (Builder $p) use ($bt, $br): void {
                $p->whereIn($bt, ['2', 'BB', 'bed_breakfast', 'B&B', 'B & B', 'bed & breakfast', 'Bed & Breakfast', 'bed and breakfast'])
                    ->orWhere($br, 'like', '%Bed & Breakfast%')
                    ->orWhere($br, 'like', '%bed and breakfast%')
                    ->orWhere($br, 'like', '%B&B%');
            }),
            'self_catering' => $package->where(function (Builder $p) use ($bt, $br): void {
                $p->whereIn($bt, ['SC', 'self_catering', 'self-catering', 'self catering', 'Self Catering', 'Self-catering'])
                    ->orWhere($br, 'like', '%self catering%')
                    ->orWhere($br, 'like', '%Self Catering%');
            }),
            'other' => $package->where(function (Builder $p) use ($bt, $br): void {
                $p->where(function (Builder $inner) use ($bt, $br): void {
                    $inner->whereNotIn($bt, [
                        '2', '3', '5', 'AI', 'BB', 'HB', 'SC', 'all_inclusive', 'half_board', 'bed_breakfast', 'self_catering',
                    ])->orWhereIn($bt, ['1', '4', 'RO', 'FB']);
                })->orWhere($br, 'like', '%Room Only%')
                    ->orWhere($br, 'like', '%Full Board%');
            }),
            default => $package->whereRaw('0 = 1'),
        };
    }
}
