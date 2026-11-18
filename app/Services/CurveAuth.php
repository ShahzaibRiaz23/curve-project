<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\PendingRequest;

class CurveAuth
{
    protected const CACHE_KEY = 'curve_access_token';

    /**
     * Get a valid access token.
     * A new one is requested only when the cached one is missing or expired.
     */
    public static function token(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $cfg = config('services.curve');

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
        ];

        if (! empty($cfg['scope'])) {
            $payload['scope'] = $cfg['scope'];
        }

        $res = Http::asForm()->acceptJson()->timeout(30)->post($cfg['token_url'], $payload);

        $token = $res->json('access_token');

        if ($res->failed() || ! $token) {
            throw new RuntimeException('Curve token request failed. HTTP '.$res->status().': '.$res->body());
        }

        // Keep the token slightly shorter than its real life so it never expires mid-request.
        $ttl = max(60, (int) $res->json('expires_in', 3600) - 120);
        Cache::put(self::CACHE_KEY, $token, $ttl);

        return $token;
    }

    /** Remove the cached token (use after changing scopes or on a 401). */
    public static function forgetToken(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** https://api.curvehero.com/v1/{instance} */
    public static function baseUrl(): string
    {
        $cfg = config('services.curve');

        return rtrim($cfg['api_base'], '/').'/'.$cfg['instance'];
    }

    /** Ready-to-use HTTP client with the bearer token attached. */
    public static function http(): PendingRequest
    {
        return Http::withToken(self::token())->acceptJson()->timeout(30);
    }

    /**
     * GET helper. Accepts '/clinic' style paths or a full URL.
     * Refreshes the token once on 401 and backs off on 429.
     */
    public static function get(string $pathOrUrl, array $query = []): Response
    {
        $url = str_starts_with($pathOrUrl, 'http')
            ? $pathOrUrl
            : self::baseUrl().'/'.ltrim($pathOrUrl, '/');

        $attempts = 0;
        $refreshed = false;

        while (true) {
            $attempts++;
            $res = self::http()->get($url, $query);

            if ($res->status() === 401 && ! $refreshed) {
                self::forgetToken();
                $refreshed = true;

                continue;
            }

            if ($res->status() === 429 && $attempts < 4) {
                sleep((int) ($res->header('Retry-After') ?: $attempts * 2));

                continue;
            }

            return $res;
        }
    }

    /**
     * POST helper. Accepts '/patient' style paths or a full URL.
     * Refreshes the token once on 401 and backs off on 429.
     */
    public static function post(string $pathOrUrl, array $data = []): Response
    {
        $url = str_starts_with($pathOrUrl, 'http')
            ? $pathOrUrl
            : self::baseUrl().'/'.ltrim($pathOrUrl, '/');

        $attempts = 0;
        $refreshed = false;

        while (true) {
            $attempts++;
            $res = self::http()->post($url, $data);

            if ($res->status() === 401 && ! $refreshed) {
                self::forgetToken();
                $refreshed = true;

                continue;
            }

            if ($res->status() === 429 && $attempts < 4) {
                sleep((int) ($res->header('Retry-After') ?: $attempts * 2));

                continue;
            }

            return $res;
        }
    }

    /**
     * PUT helper. Accepts '/appointment/1' style paths or a full URL.
     * Refreshes the token once on 401 and backs off on 429.
     */
    public static function put(string $pathOrUrl, array $data = []): Response
    {
        $url = str_starts_with($pathOrUrl, 'http')
            ? $pathOrUrl
            : self::baseUrl().'/'.ltrim($pathOrUrl, '/');

        $attempts = 0;
        $refreshed = false;

        while (true) {
            $attempts++;
            $res = self::http()->put($url, $data);

            if ($res->status() === 401 && ! $refreshed) {
                self::forgetToken();
                $refreshed = true;

                continue;
            }

            if ($res->status() === 429 && $attempts < 4) {
                sleep((int) ($res->header('Retry-After') ?: $attempts * 2));

                continue;
            }

            return $res;
        }
    }
}
