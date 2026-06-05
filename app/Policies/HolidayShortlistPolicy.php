<?php

namespace App\Policies;

use App\Models\HolidayShortlist;
use App\Models\User;

class HolidayShortlistPolicy
{
    public function view(User $user, HolidayShortlist $shortlist): bool
    {
        return $shortlist->user_id === $user->id;
    }

    public function update(User $user, HolidayShortlist $shortlist): bool
    {
        return $shortlist->user_id === $user->id;
    }

    public function delete(User $user, HolidayShortlist $shortlist): bool
    {
        return $shortlist->user_id === $user->id;
    }
}
