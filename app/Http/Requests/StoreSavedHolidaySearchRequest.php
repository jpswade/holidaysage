<?php

namespace App\Http\Requests;

use App\Enums\SavedHolidaySearchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedHolidaySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'departure_airport_code' => ['required', 'string', 'max:8'],
            'travel_start_date' => ['nullable', 'date'],
            'travel_end_date' => ['nullable', 'date', 'after_or_equal:travel_start_date'],
            'travel_date_flexibility_days' => ['nullable', 'integer', 'min:0', 'max:14'],
            'duration_min_nights' => ['required', 'integer', 'min:1', 'max:30'],
            'duration_max_nights' => ['required', 'integer', 'min:1', 'max:30', 'gte:duration_min_nights'],
            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:10'],
            'budget_total' => ['nullable', 'numeric', 'min:0'],
            'max_flight_minutes' => ['nullable', 'integer', 'min:30', 'max:1440'],
            'max_transfer_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'provider_import_url' => ['nullable', 'url', 'max:2048'],
            'board_preferences' => ['nullable', 'array'],
            'board_preferences.*' => ['string', 'max:64'],
            'feature_preferences' => ['nullable', 'array'],
            'feature_preferences.*' => ['string', 'max:64'],
            'destination_preferences' => ['nullable', 'array'],
            'destination_preferences.*' => ['string', 'max:80'],
            'status' => ['nullable', Rule::in(array_map(
                static fn (SavedHolidaySearchStatus $status): string => $status->value,
                SavedHolidaySearchStatus::cases()
            ))],
        ];
    }
}
