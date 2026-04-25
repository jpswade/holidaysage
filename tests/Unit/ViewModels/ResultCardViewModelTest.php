<?php

namespace Tests\Unit\ViewModels;

use App\Models\HolidayPackage;
use App\Models\Hotel;
use App\Models\ProviderSource;
use App\Models\ScoredHolidayOption;
use App\ViewModels\ResultCardViewModel;
use Tests\TestCase;

class ResultCardViewModelTest extends TestCase
{
    public function test_it_extracts_image_from_known_hotel_raw_attributes_path(): void
    {
        $hotel = new Hotel([
            'hotel_name' => 'Prinsotel Alba',
            'destination_name' => 'Majorca',
            'raw_attributes' => [
                'hotel_extra' => [
                    'property' => [
                        'image' => 'https://media.jet2.com/is/image/jet2/PMI_69571_Prinsotel_Alba_0718_02',
                    ],
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

    public function test_it_extracts_image_from_property_images_array_first_element(): void
    {
        $expected = 'https://media.jet2.com/is/image/jet2/AGP_70178_Iberostar_Malaga_Playa_0223_08';
        $hotel = new Hotel([
            'hotel_name' => 'Iberostar Waves Malaga Playa',
            'destination_name' => 'Torrox',
            'raw_attributes' => [
                'hotel_extra' => [
                    'property' => [
                        'images' => [
                            $expected,
                            'https://media.jet2.com/is/image/jet2/OTHER_SHOULD_NOT_WIN',
                        ],
                    ],
                ],
            ],
        ]);
        $provider = new ProviderSource(['name' => 'Jet2']);
        $package = new HolidayPackage([
            'nights' => 7,
            'price_total' => 2000,
            'price_per_person' => 1000,
            'provider_url' => '/beach/spain/test',
        ]);
        $package->setRelation('hotel', $hotel);
        $package->setRelation('providerSource', $provider);

        $scored = new ScoredHolidayOption;
        $scored->id = 2;
        $scored->overall_score = 8.0;
        $scored->rank_position = 2;
        $scored->is_disqualified = false;
        $scored->recommendation_reasons = [];
        $scored->warning_flags = [];
        $scored->setRelation('holidayPackage', $package);

        $viewModel = ResultCardViewModel::fromModel($scored);

        $this->assertSame($expected, $viewModel->imageUrl);
    }

    public function test_it_does_not_guess_image_from_random_nested_keys(): void
    {
        $hotel = new Hotel([
            'hotel_name' => 'Prinsotel Alba',
            'destination_name' => 'Majorca',
            'raw_attributes' => [
                'hotel_extra' => [
                    'meta' => [
                        'unexpected_image_field' => 'https://media.jet2.com/is/image/jet2/SHOULD_NOT_BE_USED',
                    ],
                ],
            ],
        ]);
        $provider = new ProviderSource(['name' => 'Jet2']);
        $package = new HolidayPackage([
            'nights' => 7,
            'price_total' => 1847,
            'price_per_person' => 924,
            'provider_url' => '/beach/spain/test',
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

        $this->assertNull($viewModel->imageUrl);
    }
}
