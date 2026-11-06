<?php

namespace App\Http\Controllers\OAuth;

use App\Accounts\Account;
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

        $account = $this->account($request);

        if ($account === null || ! $this->passwordMatches($account, $request->input('password'))) {
            $error = $request->filled('resource') ? 'Wrong password.' : 'Wrong user name or password.';

            return $this->form($request, $error, 422);
        }

        $code = $tokens->seal(SealedTokens::CODE, [
            'user' => $account->name,
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

        if ($request->filled('resource') && Account::fromResource($request->input('resource')) === null) {
            return $this->redirectToClient($request, ['error' => 'invalid_target']);
        }

        return null;
    }

    private function form(Request $request, ?string $error = null, int $status = 200): Response
    {
        return response()->view('oauth.authorize', [
            'params' => array_filter($request->only(self::PARAMS), is_string(...)),
            'host' => parse_url($request->input('redirect_uri'), PHP_URL_HOST),
            'account' => Account::fromResource($request->input('resource')),
            'error' => $error,
        ], $status);
    }

    /** The account comes from the resource URL, or from the user name typed on the form when a client sends no resource. */
    private function account(Request $request): ?Account
    {
        return $request->filled('resource')
            ? Account::fromResource($request->input('resource'))
            : Account::find($request->input('username'));
    }

    private function passwordMatches(Account $account, mixed $password): bool
    {
        $hash = $account->passwordHash;

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
