<?php

namespace App\Enums;

enum SavedHolidaySearchRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
