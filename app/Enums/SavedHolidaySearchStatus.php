<?php

namespace App\Enums;

enum SavedHolidaySearchStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
