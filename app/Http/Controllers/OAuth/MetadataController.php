<?php

namespace App\Http\Controllers\OAuth;

use App\Accounts\Account;
use Illuminate\Http\JsonResponse;

class MetadataController
{
    /** Each account is its own protected resource, found from the path after the well-known prefix. */
    public function protectedResource(?string $path = null): JsonResponse
    {
        $account = Account::fromResource(url('/'.$path));

        if ($account === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'resource' => $account->resourceUrl(),
            'authorization_servers' => [url('/')],
            'scopes_supported' => [config('connector.scope')],
            'bearer_methods_supported' => ['header'],
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('oauth.authorize'),
            'token_endpoint' => route('oauth.token'),
            'registration_endpoint' => route('oauth.register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => [config('connector.scope'), 'offline_access'],
        ]);
    }
}
