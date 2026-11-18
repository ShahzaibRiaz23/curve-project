<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCurveSyncKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('services.curve.sync_key');
        $providedKey = (string) ($request->header('X-Sync-Key') ?? $request->query('key') ?? '');

        if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
