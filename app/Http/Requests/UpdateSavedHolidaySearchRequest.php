<?php

namespace App\Http\Requests;

/**
 * Same validation rules as creating a saved search; used when refining criteria.
 */
class UpdateSavedHolidaySearchRequest extends StoreSavedHolidaySearchRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('feature_preferences')) {
            $this->merge(['feature_preferences' => []]);
        }
    }
}
