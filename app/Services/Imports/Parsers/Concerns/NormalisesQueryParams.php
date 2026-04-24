<?php

namespace App\Services\Imports\Parsers\Concerns;

trait NormalisesQueryParams
{
    /**
     * @param  array<int|string, mixed>  $query
     * @return array<string, string>
     */
    protected function lowerCaseKeyMap(array $query): array
    {
        $out = [];
        foreach ($query as $k => $v) {
            if (is_string($k) && (is_string($v) || is_numeric($v))) {
                $out[strtolower($k)] = (string) $v;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $candidates
     */
    protected function getQueryValue(array $lowerQuery, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            $k = strtolower($c);
            if (array_key_exists($k, $lowerQuery) && $lowerQuery[$k] !== '') {
                return $lowerQuery[$k];
            }
        }

        return null;
    }
}
