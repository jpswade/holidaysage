<?php

namespace App\Enums;

enum SavedHolidaySearchRunType: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case Import = 'import';
}
