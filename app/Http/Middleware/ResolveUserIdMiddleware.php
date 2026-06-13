<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveUserIdMiddleware
{
    /**
     * Resolve user ID from the X-User-ID header.
     * Since authentication is not required, we accept the user ID directly.
     * A real system would verify a JWT/session token here instead.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->header('X-User-ID');

        if (empty($userId) || ! ctype_digit((string) $userId) || (int) $userId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'A valid X-User-ID header is required.',
                'data'    => null,
            ], 400);
        }

        // Bind the resolved user ID so controllers/requests can access it cleanly
        $request->merge(['_resolved_user_id' => (int) $userId]);

        return $next($request);
    }
}
