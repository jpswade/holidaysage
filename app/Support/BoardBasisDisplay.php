<?php

namespace App\Support;

/**
 * Human-readable board basis for UI (Jet2 numeric ids, letter codes, and slugs).
 *
 * Jet2 payloads commonly use numeric boardId 1–5 (room only through all inclusive). Higher
 * numeric ids are provider-specific: we only label those when {@see $boardRecommended} supplies text.
 */
final class BoardBasisDisplay
{
    /** @var array<string, string> */
    private const LABELS = [
        '1' => 'Room Only',
        '2' => 'Bed & Breakfast',
        '3' => 'Half Board',
        '4' => 'Full Board',
        '5' => 'All Inclusive',
        'AI' => 'All Inclusive',
        'FB' => 'Full Board',
        'HB' => 'Half Board',
        'BB' => 'Bed & Breakfast',
        'SC' => 'Self Catering',
        'RO' => 'Room Only',
    ];

    public static function humanLabel(?string $boardType, ?string $boardRecommended): ?string
    {
        $type = $boardType !== null ? trim((string) $boardType) : '';
        $recommended = $boardRecommended !== null ? trim((string) $boardRecommended) : '';

        if ($type !== '') {
            $code = strtoupper($type);
            if (isset(self::LABELS[$type])) {
                return self::LABELS[$type];
            }
            if (isset(self::LABELS[$code])) {
                return self::LABELS[$code];
            }
            if (str_contains($type, '_')) {
                return ucwords(str_replace('_', ' ', $type));
            }
        }

        if ($recommended !== '') {
            return $recommended;
        }

        if ($type !== '' && ! ctype_digit($type)) {
            return ucwords(str_replace('_', ' ', $type));
        }

        return null;
    }
}
