<?php

namespace App\Services\ProviderImport;

use App\Support\SyncQueueLine;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Guzzle-based GET for Jet2 smartsearch. Does not use the Laravel
 * Http client: its PendingRequest is built with asJson() and taints
 * every request with JSON/Content-Type defaults.
 */
class Jet2SmartSearchHttpClient
{
    public function __construct(
        private ?Client $client = null
    ) {
        $this->client ??= new Client;
    }

    public function get(string $url, bool $isApi): Response
    {
        $t = $this->timeouts($isApi);
        $config = array_merge(
            $this->baseGuzzleConfig($t),
            ['headers' => $this->browserLikeHeadersForApiGet()]
        );

        $shortUrl = Str::limit($url, 100, '…');
        Log::info('holidaysage.jet2.get.start', [
            'url' => $shortUrl,
            'is_api' => $isApi,
            'connect_timeout' => $t['connect'],
            'timeout' => $t['request'],
            'max_5xx_attempts' => $t['max5xx'],
        ]);
        SyncQueueLine::line(sprintf(
            'Jet2 HTTP: requesting (up to %.0fs connect + %.0fs)…',
            $t['connect'],
            $t['request']
        ));

        for ($attempt = 1; $attempt <= $t['max5xx']; $attempt++) {
            $started = hrtime(true);
            try {
                $psr = $this->client->get($url, $config);
            } catch (GuzzleConnectException $e) {
                Log::debug('holidaysage.jet2.get.guzzle_connect_failed', [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                    'url' => Str::limit($url, 200, '…'),
                ]);
                $fallback = $this->requestViaPhpCurl($url, $isApi, $t);
                if ($fallback !== null) {
                    $this->logExtCurlSuccess($url, $isApi, $started, $fallback);

                    return $fallback;
                }

                throw new \RuntimeException(
                    'Jet2 HTTP request failed (Guzzle connection/timeout and ext-curl fallback exhausted): '
                    .Str::limit($e->getMessage(), 500)
                    .' | URL: '.Str::limit($url, 200, '…'),
                    0,
                    $e
                );
            }
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);
            $code = $psr->getStatusCode();
            if ($code >= 500 && $code < 600 && $attempt < $t['max5xx']) {
                Log::warning('holidaysage.jet2.get.5xx_retry', [
                    'status' => $code,
                    'attempt' => $attempt,
                ]);
                if ($t['sleepMs'] > 0) {
                    usleep($t['sleepMs'] * 1000);
                }

                continue;
            }
            Log::info('holidaysage.jet2.get.done', [
                'url' => Str::limit($url, 120, '…'),
                'status' => $code,
                'ms' => $ms,
                'attempt' => $attempt,
            ]);
            SyncQueueLine::line(sprintf('Jet2 HTTP: %d in %dms (attempt %d).', $code, $ms, $attempt));

            return new Response($psr);
        }

        throw new \RuntimeException('Jet2 HTTP: no response after '.$t['max5xx'].' attempt(s).');
    }

    /**
     * @param  array{connect: float, request: float, max5xx: int, sleepMs: int}  $t
     * @return array<string, mixed>
     */
    private function baseGuzzleConfig(array $t): array
    {
        return [
            'connect_timeout' => $t['connect'],
            'timeout' => $t['request'],
            'http_errors' => false,
            'allow_redirects' => true,
            'decode_content' => true,
        ];
    }

    /**
     * @return array{connect: float, request: float, max5xx: int, sleepMs: int}
     */
    private function timeouts(bool $isApi): array
    {
        $j = (array) config('holidaysage.jet2', []);

        return [
            'connect' => (float) ($j['connect_timeout'] ?? 5.0),
            'request' => $isApi
                ? (float) ($j['api_timeout'] ?? 12.0)
                : (float) ($j['html_timeout'] ?? 16.0),
            'max5xx' => max(1, (int) ($j['max_5xx_attempts'] ?? 2)),
            'sleepMs' => max(0, (int) ($j['retry_5xx_sleep_ms'] ?? 300)),
        ];
    }

    /**
     * Match `test.sh` in the repo (Chrome “open URL in tab” / navigation).
     *
     * @return array<string, string>
     */
    public function browserLikeHeadersForApiGet(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => 'en-GB,en-US;q=0.9,en;q=0.8,pt;q=0.7',
            'Cache-Control' => 'max-age=0',
            'DNT' => '1',
            'Priority' => 'u=0, i',
            'Sec-Ch-Ua' => '"Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
            'Sec-Ch-Ua-Arch' => '"arm"',
            'Sec-Ch-Ua-Bitness' => '"64"',
            'Sec-Ch-Ua-Full-Version-List' => '"Chromium";v="146.0.7680.165", "Not-A.Brand";v="24.0.0.0", "Google Chrome";v="146.0.7680.165"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Model' => '""',
            'Sec-Ch-Ua-Platform' => '"macOS"',
            'Sec-Ch-Ua-Platform-Version' => '"26.3.1"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Sec-GPC' => '1',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        ];
    }

    private function logExtCurlSuccess(string $url, bool $isApi, int|float|bool $started, Response $response): void
    {
        $ms = (int) round(((float) hrtime(true) - (float) $started) / 1_000_000);
        $code = $response->status();
        Log::info('holidaysage.jet2.get.ext_curl', [
            'url' => Str::limit($url, 120, '…'),
            'is_api' => $isApi,
            'status' => $code,
            'ms' => $ms,
        ]);
        SyncQueueLine::line(sprintf('Jet2 HTTP: %d in %dms (ext-curl fallback).', $code, $ms));
    }

    /**
     * @param  array{connect: float, request: float, max5xx: int, sleepMs: int}  $t
     */
    private function requestViaPhpCurl(string $url, bool $isApi, array $t): ?Response
    {
        if (app()->runningUnitTests() || ! function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $requestTimeout = (int) max(1, (int) round($t['request']));
        $connectTimeout = (int) max(1, (int) round($t['connect']));

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $this->toCurlHeaderLines($this->browserLikeHeadersForApiGet()),
            CURLOPT_TIMEOUT => $requestTimeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_ENCODING => '',
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            curl_close($ch);

            return null;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status < 1) {
            return null;
        }

        return new Response(new \GuzzleHttp\Psr7\Response($status, [], $body));
    }

    /**
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    private function toCurlHeaderLines(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $out[] = strtolower($name).': '.$value;
        }

        return $out;
    }
}
