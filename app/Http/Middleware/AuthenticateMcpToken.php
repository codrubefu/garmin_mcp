<?php

namespace App\Http\Middleware;

use App\Accounts\Account;
use App\OAuth\SealedTokens;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpToken
{
    public function __construct(private SealedTokens $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = Account::find($request->route('user'));
        $claims = $this->tokens->open($request->bearerToken(), SealedTokens::ACCESS);

        // The audience ties a token to one account, so it cannot be replayed on another user's endpoint.
        if ($account === null || $claims === null || ($claims['aud'] ?? null) !== $account->resourceUrl()) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        // The Garmin client and every tool now read this account's data.
        app()->instance(Account::class, $account);

        return $next($request);
    }
}
