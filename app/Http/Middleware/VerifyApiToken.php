<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth sederhana token statis (Bearer) untuk REST API bridging.
 * Kirim header: Authorization: Bearer <INCIDENT_API_TOKEN>
 */
class VerifyApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.incident_bridge.token');
        $provided = (string) ($request->bearerToken() ?? $request->header('X-Api-Token', ''));

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized — token tidak valid.'], 401);
        }

        return $next($request);
    }
}
