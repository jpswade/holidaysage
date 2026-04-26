<?php

namespace Tests\Unit\ViewModels;

use App\Models\HolidayPackage;
use App\Models\Hotel;
use App\Models\HotelPhoto;
use App\Models\ProviderSource;
use App\Models\ScoredHolidayOption;
use App\ViewModels\ResultCardViewModel;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultCardViewModelTest extends TestCase
{
    public function test_it_uses_structured_hotel_images_json_for_display_url(): void
    {
        $hotel = new Hotel([
            'hotel_name' => 'Prinsotel Alba',
            'destination_name' => 'Majorca',
            'images' => [
                [
                    'url' => 'https://media.jet2.com/is/image/jet2/PMI_69571_Prinsotel_Alba_0718_02',
                    'source' => 'jet2_json_ld',
                    'position' => 0,
                ],
            ],
        ]);
        $provider = new ProviderSource(['name' => 'Jet2']);
        $package = new HolidayPackage([
            'nights' => 7,
            'price_total' => 1847,
            'price_per_person' => 924,
            'provider_url' => '/beach/spain/test',
            'flight_outbound_duration_minutes' => 255,
        ]);
        $package->setRelation('hotel', $hotel);
        $package->setRelation('providerSource', $provider);

        $scored = new ScoredHolidayOption;
        $scored->id = 1;
        $scored->overall_score = 9.4;
        $scored->rank_position = 1;
        $scored->is_disqualified = false;
        $scored->recommendation_reasons = [];
        $scored->warning_flags = [];
        $scored->setRelation('holidayPackage', $package);

        $viewModel = ResultCardViewModel::fromModel($scored);

        $this->assertSame(
            'https://media.jet2.com/is/image/jet2/PMI_69571_Prinsotel_Alba_0718_02',
            $viewModel->imageUrl
        );
    }

    public function test_it_prefers_locally_cached_hotel_photo_url(): void
    {
        Storage::fake('public');
        $path = 'hotel-photos/9/test-hash.jpg';
        Storage::disk('public')->put($path, 'x');

        $hotel = new Hotel([
            'id' => 9,
            'hotel_name' => 'Test',
            'destination_name' => 'Here',
            'images' => [
                ['url' => 'https://media.jet2.com/is/image/jet2/remote_only', 'source' => 'jet2_json_ld', 'position' => 0],
            ],
        ]);
        $photo = new HotelPhoto([
            'hotel_id' => 9,
            'position' => 0,
            'external_url' => 'https://media.jet2.com/is/image/jet2/cached',
            'external_url_hash' => hash('sha256', 'https://media.jet2.com/is/image/jet2/cached'),
            'file_path' => $path,
            'status' => HotelPhoto::STATUS_CACHED,
        ]);
        $hotel->setRelation('photos', collect([$photo]));

        $package = new HolidayPackage(['nights' => 7, 'price_total' => 100, 'price_per_person' => 50, 'provider_url' => '/x']);
        $package->setRelation('hotel', $hotel);
        $package->setRelation('providerSource', new ProviderSource(['name' => 'Jet2']));

        $scored = new ScoredHolidayOption;
        $scored->id = 1;
        $scored->overall_score = 8.0;
        $scored->rank_position = 1;
        $scored->is_disqualified = false;
        $scored->recommendation_reasons = [];
        $scored->warning_flags = [];
        $scored->setRelation('holidayPackage', $package);

        $viewModel = ResultCardViewModel::fromModel($scored);

        $this->assertSame(Storage::disk('public')->url($path), $viewModel->imageUrl);
    }

    public function test_it_returns_null_when_no_modelled_images(): void
    {
        $hotel = new Hotel([
            'hotel_name' => 'X',
            'destination_name' => 'Y',
        ]);
        $hotel->setRelation('photos', collect());

        $package = new HolidayPackage(['nights' => 7, 'price_total' => 1, 'price_per_person' => 1, 'provider_url' => '/x']);
        $package->setRelation('hotel', $hotel);
        $package->setRelation('providerSource', new ProviderSource(['name' => 'Jet2']));

        $scored = new ScoredHolidayOption;
        $scored->id = 1;
        $scored->overall_score = 5.0;
        $scored->rank_position = 1;
        $scored->is_disqualified = false;
        $scored->recommendation_reasons = [];
        $scored->warning_flags = [];
        $scored->setRelation('holidayPackage', $package);

        $this->assertNull(ResultCardViewModel::fromModel($scored)->imageUrl);
    }

    public function test_recommendation_blurb_prefers_hotel_introduction_over_templated_scorer_summary(): void
    {
        $hotel = new Hotel([
            'hotel_name' => 'Xanadu Resort Hotel Belek',
            'destination_name' => 'Antalya Area',
            'introduction_snippet' => 'A luxury five-star beachfront resort with extensive pools, spa facilities, and a private stretch of sand. Family-friendly dining and evening entertainment are included year-round.',
        ]);
        $package = new HolidayPackage(['nights' => 7, 'price_total' => 2000, 'price_per_person' => 1000, 'provider_url' => '/x']);
        $package->setRelation('hotel', $hotel);
        $package->setRelation('providerSource', new ProviderSource(['name' => 'Jet2']));

        $scored = new ScoredHolidayOption;
        $scored->id = 1;
        $scored->overall_score = 7.5;
        $scored->rank_position = 1;
        $scored->is_disqualified = false;
        $scored->recommendation_summary = 'Solid fit: Xanadu Resort Hotel Belek in Antalya Area scores 7.5/10. Strong value for this holiday type';
        $scored->recommendation_reasons = ['Some reason'];
        $scored->warning_flags = [];
        $scored->setRelation('holidayPackage', $package);

        $blurb = ResultCardViewModel::fromModel($scored)->recommendationBlurb;

        $this->assertStringContainsString('five-star beachfront', $blurb);
        $this->assertStringNotContainsString('Solid fit:', $blurb);
    }

    public function test_recommendation_blurb_uses_reasons_instead_of_templated_scorer_summary_when_no_intro(): void
    {
        $hotel = new Hotel([
            'hotel_name' => 'Test Hotel',
            'destination_name' => 'Majorca',
        ]);
        $package = new HolidayPackage(['nights' => 7, 'price_total' => 2000, 'price_per_person' => 1000, 'provider_url' => '/x']);
        $package->setRelation('hotel', $hotel);
        $package->setRelation('providerSource', new ProviderSource(['name' => 'Jet2']));

        $scored = new ScoredHolidayOption;
        $scored->id = 1;
        $scored->overall_score = 7.5;
        $scored->rank_position = 1;
        $scored->is_disqualified = false;
        $scored->recommendation_summary = 'Solid fit: Test Hotel in Majorca scores 7.5/10. Value line';
        $scored->recommendation_reasons = ['Competitive package price for this board and season', 'Kids club on site'];
        $scored->warning_flags = [];
        $scored->setRelation('holidayPackage', $package);

        $blurb = ResultCardViewModel::fromModel($scored)->recommendationBlurb;

        $this->assertStringContainsString('Kids club on site', $blurb);
        $this->assertStringNotContainsString('Solid fit:', $blurb);
    }
}
