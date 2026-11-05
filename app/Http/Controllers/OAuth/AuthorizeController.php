<?php

namespace App\Http\Controllers\OAuth;

use App\OAuth\RedirectUris;
use App\OAuth\SealedTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class AuthorizeController
{
    private const PARAMS = ['client_id', 'redirect_uri', 'response_type', 'state', 'scope', 'code_challenge', 'code_challenge_method', 'resource'];

    public function show(Request $request): BaseResponse
    {
        return $this->rejection($request) ?? $this->form($request);
    }

    public function store(Request $request, SealedTokens $tokens): BaseResponse
    {
        if ($rejection = $this->rejection($request)) {
            return $rejection;
        }

        if (! $this->passwordMatches($request->input('password'))) {
            return $this->form($request, 'Wrong password.', 422);
        }

        $code = $tokens->seal(SealedTokens::CODE, [
            'client_id' => $request->input('client_id'),
            'redirect_uri' => $request->input('redirect_uri'),
            'code_challenge' => $request->input('code_challenge'),
            'resource' => $request->input('resource'),
        ], config('connector.code_ttl'));

        return $this->redirectToClient($request, ['code' => $code, 'iss' => url('/')]);
    }

    /** Returns the response that ends the flow, or null if the request is acceptable. */
    private function rejection(Request $request): ?BaseResponse
    {
        // A bad redirect must never be followed, so this one is shown instead of redirected.
        if (! is_string($request->input('client_id')) || ! RedirectUris::allows($request->input('redirect_uri'))) {
            return response('Invalid client_id or redirect_uri.', 400);
        }

        if ($request->input('response_type') !== 'code') {
            return $this->redirectToClient($request, ['error' => 'unsupported_response_type']);
        }

        $challengeIsValid = $request->input('code_challenge_method') === 'S256'
            && is_string($request->input('code_challenge'))
            && preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $request->input('code_challenge')) === 1;

        if (! $challengeIsValid) {
            return $this->redirectToClient($request, ['error' => 'invalid_request', 'error_description' => 'S256 PKCE is required.']);
        }

        if ($request->filled('resource') && $request->input('resource') !== url('/mcp')) {
            return $this->redirectToClient($request, ['error' => 'invalid_target']);
        }

        return null;
    }

    private function form(Request $request, ?string $error = null, int $status = 200): Response
    {
        return response()->view('oauth.authorize', [
            'params' => array_filter($request->only(self::PARAMS), is_string(...)),
            'host' => parse_url($request->input('redirect_uri'), PHP_URL_HOST),
            'error' => $error,
        ], $status);
    }

    private function passwordMatches(mixed $password): bool
    {
        $hash = config('connector.password_hash');

        return is_string($password) && is_string($hash) && $hash !== '' && Hash::check($password, $hash);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function redirectToClient(Request $request, array $params): RedirectResponse
    {
        if (is_string($request->input('state'))) {
            $params['state'] = $request->input('state');
        }

        return redirect()->away($request->input('redirect_uri').'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }
}
