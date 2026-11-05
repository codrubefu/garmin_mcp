<?php

namespace App\Http\Controllers\OAuth;

use App\OAuth\RedirectUris;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegisterController
{
    /** Nothing is stored: the client ID is a label, and redirects are checked on every request. */
    public function __invoke(Request $request): JsonResponse
    {
        $uris = $request->json('redirect_uris');

        if (! is_array($uris) || $uris === [] || ! collect($uris)->every(RedirectUris::allows(...))) {
            return response()->json([
                'error' => 'invalid_redirect_uri',
                'error_description' => 'This server only accepts Claude redirect URIs.',
            ], 400);
        }

        return response()->json([
            'client_id' => Str::random(40),
            'client_id_issued_at' => time(),
            'redirect_uris' => array_values($uris),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }
}
