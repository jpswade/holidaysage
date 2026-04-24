<?php

namespace App\Services\ProviderImport\Importers\Concerns;

trait ExtractsEmbeddedJson
{
    /**
     * @return list<array<string,mixed>>
     */
    protected function extractJsonDocuments(string $html): array
    {
        $docs = [];

        if (preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $m)) {
            foreach ($m[1] as $json) {
                $json = trim(html_entity_decode((string) $json));
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $docs[] = $decoded;
                }
            }
        }

        return $docs;
    }
}
