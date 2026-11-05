<?php

namespace App\Http\Middleware;

use App\OAuth\SealedTokens;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpToken
{
    public function __construct(private SealedTokens $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $claims = $this->tokens->open($request->bearerToken(), SealedTokens::ACCESS);

        if ($claims === null || ($claims['aud'] ?? null) !== url('/mcp')) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        return $next($request);
    }
}
