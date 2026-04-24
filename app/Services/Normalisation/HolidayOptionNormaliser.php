<?php

namespace App\Services\Normalisation;

use App\Models\HolidayOption;
use App\Models\ProviderSource;

class HolidayOptionNormaliser
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normaliseAndSign(array $raw, ProviderSource $provider): array
    {
        $data = $this->applyBoardAliases($raw);
        if (empty($data['provider_url']) || ! is_string($data['provider_url'])) {
            $data['provider_url'] = $provider->base_url;
        }
        if (empty($data['currency'])) {
            $data['currency'] = 'GBP';
        }
        if (! isset($data['signature_hash']) || ! is_string($data['signature_hash']) || $data['signature_hash'] === '') {
            $data['signature_hash'] = $this->buildSignature($provider, $data);
        }

        return $data;
    }

    public function upsert(ProviderSource $provider, array $normalised, ?\DateTimeInterface $now = null): HolidayOption
    {
        $now = $now ?? now();
        $normalised['provider_source_id'] = $provider->id;
        $model = HolidayOption::query()->where([
            'provider_source_id' => $provider->id,
            'signature_hash' => $normalised['signature_hash'],
        ])->first();

        if (! $model) {
            $normalised['first_seen_at'] = $now;
        }
        $normalised['last_seen_at'] = $now;
        $fill = array_intersect_key(
            $normalised,
            array_flip((new HolidayOption)->getFillable())
        );
        if (! $model) {
            return HolidayOption::query()->create($fill);
        }
        $model->fill($fill);
        $model->save();

        return $model;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildSignature(ProviderSource $provider, array $data): string
    {
        $parts = [
            (string) $provider->id,
            (string) ($data['provider_option_id'] ?? ''),
            (string) ($data['departure_date'] ?? ''),
            (string) ($data['nights'] ?? ''),
            strtolower((string) ($data['hotel_name'] ?? '')),
            (string) ($data['board_type'] ?? ''),
            (string) ($data['adults'] ?? '').'-'.(string) ($data['children'] ?? '').'-'.(string) ($data['infants'] ?? ''),
        ];
        $payload = implode('|', $parts);

        return hash('sha256', $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyBoardAliases(array $data): array
    {
        if (! isset($data['board_type']) || ! is_string($data['board_type'])) {
            return $data;
        }
        $b = strtoupper(trim($data['board_type']));
        if (in_array($b, ['AI', 'ALL INCLUSIVE', 'ALL-IN', 'ALL_INCLUSIVE', 'AL'], true) || str_contains($b, 'ALL INC')) {
            $data['board_type'] = 'all_inclusive';
        } elseif (in_array($b, ['HB', 'HALF BOARD', 'HALF_BOARD', 'H/B'], true) || str_contains($b, 'HALF BO')) {
            $data['board_type'] = 'half_board';
        } elseif (in_array($b, ['SC', 'SELF CATERING', 'SELF_CATERING', 'S/C'], true) || str_contains($b, 'SELF CAT')) {
            $data['board_type'] = 'self_catering';
        }

        return $data;
    }
}
