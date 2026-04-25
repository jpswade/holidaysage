<?php

namespace Tests\Unit\Support;

use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Support\SavedHolidaySearchDisplayName;
use Carbon\Carbon;
use Tests\TestCase;

class SavedHolidaySearchDisplayNameTest extends TestCase
{
    public function test_it_builds_from_extracted_criteria(): void
    {
        $provider = new ProviderSource(['key' => 'jet2', 'name' => 'Jet2holidays']);
        $name = SavedHolidaySearchDisplayName::fromExtracted([
            'departure_airport_code' => 'MAN',
            'travel_start_date' => '2026-07-15',
            'travel_end_date' => '2026-07-25',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ], $provider);

        $this->assertStringStartsWith('Jet2 · MAN', $name);
        $this->assertStringContainsString('15 Jul–25 Jul 2026', $name);
        $this->assertStringContainsString('10 nights', $name);
    }

    public function test_it_includes_party_when_not_default_two_adults(): void
    {
        $provider = new ProviderSource(['key' => 'jet2', 'name' => 'Jet2holidays']);
        $name = SavedHolidaySearchDisplayName::fromExtracted([
            'departure_airport_code' => 'LGW',
            'travel_start_date' => '2026-08-01',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
        ], $provider);

        $this->assertStringContainsString('2 adults, 1 child', $name);
    }

    public function test_it_skips_numeric_only_destination_preferences(): void
    {
        $provider = new ProviderSource(['key' => 'jet2', 'name' => 'Jet2holidays']);
        $name = SavedHolidaySearchDisplayName::fromExtracted([
            'departure_airport_code' => 'MAN',
            'travel_start_date' => '2026-07-15',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'destination_preferences' => ['39', 'Mallorca'],
        ], $provider);

        $this->assertStringContainsString('Mallorca', $name);
        $this->assertStringNotContainsString('39', $name);
    }

    public function test_should_auto_replace_detects_legacy_import_style(): void
    {
        $this->assertTrue(SavedHolidaySearchDisplayName::shouldAutoReplaceStoredName('Import — Jet2holidays (www.jet2holidays.com)'));
        $this->assertTrue(SavedHolidaySearchDisplayName::shouldAutoReplaceStoredName('import from jet2'));
        $this->assertFalse(SavedHolidaySearchDisplayName::shouldAutoReplaceStoredName('Family week in Majorca'));
    }

    public function test_is_generic_provider_search_name(): void
    {
        $provider = new ProviderSource(['key' => 'jet2', 'name' => 'Jet2holidays']);
        $this->assertTrue(SavedHolidaySearchDisplayName::isGenericProviderSearchName('Jet2holidays Search', $provider));
        $this->assertFalse(SavedHolidaySearchDisplayName::isGenericProviderSearchName('Jet2 · MAN · 2026', $provider));
    }

    public function test_from_saved_search_matches_model_fields(): void
    {
        $provider = new ProviderSource(['key' => 'tui', 'name' => 'TUI']);
        $search = new SavedHolidaySearch([
            'departure_airport_code' => 'MAN',
            'travel_start_date' => Carbon::parse('2026-06-10'),
            'travel_end_date' => Carbon::parse('2026-06-20'),
            'duration_min_nights' => 7,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ]);

        $name = SavedHolidaySearchDisplayName::fromSavedSearch($search, $provider);
        $this->assertStringStartsWith('TUI · MAN', $name);
        $this->assertStringContainsString('7–10 nights', $name);
    }
}
