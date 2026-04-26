<?php

namespace App\Support;

/**
 * Some valid CDN image URLs (e.g. long Scene7-style paths) fail PHP's FILTER_VALIDATE_URL; use
 * a fallback check so `hotels.images` and downloads are not dropped incorrectly.
 */
final class PlausibleHttpImageUrl
{
    public static function is(string $url): bool
    {
        $t = trim($url);
        if (filter_var($t, FILTER_VALIDATE_URL)) {
            return true;
        }
        if ($t === '' || (! str_starts_with($t, 'http://') && ! str_starts_with($t, 'https://'))) {
            return false;
        }
        $parts = @parse_url($t);

        return is_array($parts)
            && ! empty($parts['host'])
            && in_array($parts['scheme'] ?? null, ['http', 'https'], true);
    }
}
