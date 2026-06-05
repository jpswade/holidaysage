<?php

namespace Tests\Feature\HolidaySage;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Enums\SavedHolidaySearchRunType;
use App\Models\HolidayPackage;
use App\Models\HolidayShortlist;
use App\Models\Hotel;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use App\Models\User;
use Database\Seeders\ProviderSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SavedShortlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_login_prompt_on_saved_page(): void
    {
        $response = $this->get(route('saved.index'));
        $response->assertOk();
        $response->assertSeeText('Sign in to start a shortlist');
    }

    public function test_authenticated_user_can_add_remove_items(): void
    {
        $user = User::factory()->create();
        $option = $this->seedCanonicalOption();

        $this->actingAs($user)
            ->post(route('saved.items.store'), [
                'scored_holiday_option_id' => $option->id,
            ])
            ->assertRedirect();

        $shortlist = HolidayShortlist::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, $shortlist->items()->count());

        $response = $this->actingAs($user)->get(route('saved.index'));
        $response->assertOk();
        $response->assertSeeText('Test Resort');

        $item = $shortlist->items()->firstOrFail();
        $this->actingAs($user)
            ->delete(route('saved.items.destroy', ['item' => $item->id]))
            ->assertRedirect(route('saved.index'));

        $this->assertSame(0, $shortlist->fresh()->items()->count());
    }

    public function test_shared_shortlist_is_404_when_sharing_disabled_and_200_when_enabled(): void
    {
        $user = User::factory()->create();
        $option = $this->seedCanonicalOption();
        $shortlist = HolidayShortlist::query()->create(['user_id' => $user->id]);
        $shortlist->items()->create([
            'scored_holiday_option_id' => $option->id,
            'position' => 1,
        ]);
        $token = $shortlist->ensureShareToken();

        $this->get(route('shortlists.shared.show', ['token' => $token]))->assertNotFound();

        $shortlist->enableSharing();

        $response = $this->get(route('shortlists.shared.show', ['token' => $token]));
        $response->assertOk();
        $response->assertSeeText('Shared shortlist');
        $response->assertSeeText('Test Resort');
    }

    private function seedCanonicalOption(): ScoredHolidayOption
    {
        $this->seed(ProviderSourceSeeder::class);
        $provider = ProviderSource::query()->where('key', 'jet2')->firstOrFail();

        $search = SavedHolidaySearch::query()->create([
            'name' => 'Seed search',
            'slug' => 'seed-search-'.Str::random(4),
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 0,
            'status' => 'active',
        ]);
        $run = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => SavedHolidaySearchRunType::Manual,
            'status' => SavedHolidaySearchRunStatus::Completed,
            'started_at' => now()->subMinutes(8),
            'finished_at' => now()->subMinutes(4),
            'imported_holiday_package_ids' => [],
        ]);
        $hotel = Hotel::query()->create([
            'provider_source_id' => $provider->id,
            'provider_hotel_id' => 'H-1',
            'hotel_identity_hash' => hash('sha256', 'hotel-1'),
            'hotel_name' => 'Test Resort',
            'hotel_slug' => 'test-resort',
            'canonical_property_slug' => 'test-resort',
            'destination_name' => 'Majorca',
            'destination_country' => 'Spain',
            'review_score' => 4.5,
            'review_count' => 100,
        ]);
        $package = HolidayPackage::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_id' => $hotel->id,
            'provider_option_id' => 'OPT-1',
            'provider_url' => 'https://example.com/opt-1',
            'airport_code' => 'MAN',
            'departure_date' => '2026-07-15',
            'return_date' => '2026-07-22',
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'board_type' => 'all_inclusive',
            'price_total' => 1500,
            'price_per_person' => 750,
            'currency' => 'GBP',
            'flight_outbound_duration_minutes' => 220,
            'flight_inbound_duration_minutes' => 215,
            'transfer_minutes' => 30,
            'signature_hash' => hash('sha256', 'sig-1'),
        ]);

        return ScoredHolidayOption::query()->create([
            'saved_holiday_search_id' => $search->id,
            'saved_holiday_search_run_id' => $run->id,
            'holiday_package_id' => $package->id,
            'overall_score' => 9.0,
            'travel_score' => 8.5,
            'value_score' => 8.5,
            'family_fit_score' => 8.5,
            'location_score' => 8.5,
            'board_score' => 8.5,
            'price_score' => 8.5,
            'is_disqualified' => false,
            'warning_flags' => [],
            'recommendation_summary' => 'A canonical option.',
            'recommendation_reasons' => [],
            'rank_position' => 1,
        ]);
    }
}
