<?php

namespace App\ViewModels;

/**
 * One provider's canonical scored options for a unified property.
 */
final class UnifiedPropertyProviderGroupViewModel
{
    /**
     * @param  list<array{viewModel: ResultCardViewModel, option: \App\Models\ScoredHolidayOption}>  $options
     */
    public function __construct(
        public readonly string $providerKey,
        public readonly string $providerName,
        public readonly array $options,
    ) {}
}
