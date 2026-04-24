<?php

namespace App\Contracts;

interface ProviderDetailPageParser
{
    /**
     * @param  array<string,mixed>  $candidate
     * @return array{hotel: array<string,mixed>, packages: list<array<string,mixed>>}
     */
    public function parse(array $candidate, string $html): array;
}
