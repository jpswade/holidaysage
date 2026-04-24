<?php

namespace App\Contracts;

interface ImportUrlParser
{
    public function supports(string $url): bool;

    /**
     * Extract criteria compatible with `saved_holiday_searches` fillable fields (partial).
     * Keys use snake_case / model attribute names.
     *
     * @return array<string, mixed>
     */
    public function parse(string $url): array;
}
