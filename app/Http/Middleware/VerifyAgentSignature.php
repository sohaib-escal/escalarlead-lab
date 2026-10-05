<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gateway proves it is the gateway.
 *
 * HMAC over `timestamp.body` with a shared secret, compared in constant time,
 * and the timestamp must be recent so a captured request cannot be replayed
 * tomorrow. Without a configured secret the route refuses outright — an
 * unauthenticated endpoint that creates leads and spends model credits is not
 * a thing we ship by accident.
 */
class VerifyAgentSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('agent.api.secret');

        if (blank($secret)) {
            return response()->json([
                'error' => 'agent_api_disabled',
                'message' => 'AGENT_API_SECRET is not configured.',
            ], 503);
        }

        $timestamp = (string) $request->header('X-Agent-Timestamp');
        $provided = (string) $request->header('X-Agent-Signature');

        if (blank($timestamp) || blank($provided)) {
            return $this->reject('Missing X-Agent-Timestamp or X-Agent-Signature.');
        }

        if (abs(time() - (int) $timestamp) > config('agent.api.tolerance')) {
            return $this->reject('Signature timestamp is outside the accepted window.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $provided)) {
            return $this->reject('Signature mismatch.');
        }

        return $next($request);
    }

    private function reject(string $message): Response
    {
        return response()->json(['error' => 'invalid_signature', 'message' => $message], 401);
    }
}
