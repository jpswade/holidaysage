<?php

namespace App\Data;

final readonly class ScoreBreakdown
{
    /**
     * @param  list<string>  $disqualificationReasons
     * @param  list<string>  $warningFlags
     * @param  list<string>  $recommendationReasons
     */
    public function __construct(
        public float $overallScore,
        public float $travelScore,
        public float $valueScore,
        public float $reviewsScore,
        public float $familyFitScore,
        public float $locationScore,
        public float $boardScore,
        public float $priceScore,
        public bool $isDisqualified,
        public array $disqualificationReasons,
        public array $warningFlags,
        public ?string $recommendationSummary,
        public array $recommendationReasons,
    ) {}

}
