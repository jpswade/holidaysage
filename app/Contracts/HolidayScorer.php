<?php

namespace App\Contracts;

use App\Data\ScoreBreakdown;
use App\Models\HolidayOption;
use App\Models\SavedHolidaySearch;

interface HolidayScorer
{
    public function score(SavedHolidaySearch $search, HolidayOption $option): ScoreBreakdown;
}
