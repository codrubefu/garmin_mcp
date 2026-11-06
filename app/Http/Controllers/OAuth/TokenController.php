<?php

namespace App\Http\Controllers\OAuth;

use App\Accounts\Account;
use App\OAuth\SealedTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController
{
    public function __construct(private SealedTokens $tokens) {}

    public function __invoke(Request $request): JsonResponse
    {
        return match ($request->input('grant_type')) {
            'authorization_code' => $this->exchangeCode($request),
            'refresh_token' => $this->refresh($request),
            default => $this->error('unsupported_grant_type'),
        };
    }

    private function exchangeCode(Request $request): JsonResponse
    {
        $claims = $this->tokens->open($request->input('code'), SealedTokens::CODE);
        $account = Account::find($claims['user'] ?? null);

        $isValid = $claims !== null
            && $account !== null
            && $request->input('client_id') === $claims['client_id']
            && $request->input('redirect_uri') === $claims['redirect_uri']
            && $this->verifierMatches($request->input('code_verifier'), $claims['code_challenge'])
            && (! $request->filled('resource') || $request->input('resource') === $account->resourceUrl())
            && $this->tokens->consume($claims);

        return $isValid ? $this->issue($claims['client_id'], $account) : $this->error('invalid_grant');
    }

    private function refresh(Request $request): JsonResponse
    {
        $claims = $this->tokens->open($request->input('refresh_token'), SealedTokens::REFRESH);

        // Removing an account from MCP_USERS stops its refresh tokens working.
        $account = Account::find($claims['user'] ?? null);

        $isValid = $claims !== null
            && $account !== null
            && (! $request->filled('client_id') || $request->input('client_id') === $claims['client_id'])
            && $this->tokens->consume($claims);

        return $isValid ? $this->issue($claims['client_id'], $account) : $this->error('invalid_grant');
    }

    private function verifierMatches(mixed $verifier, string $challenge): bool
    {
        if (! is_string($verifier) || preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier) !== 1) {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }

    private function issue(string $clientId, Account $account): JsonResponse
    {
        $accessTtl = config('connector.access_token_ttl');
        $scope = config('connector.scope');

        return response()->json([
            'access_token' => $this->tokens->seal(SealedTokens::ACCESS, [
                'aud' => $account->resourceUrl(),
                'user' => $account->name,
                'client_id' => $clientId,
                'scope' => $scope,
            ], $accessTtl),
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
            'refresh_token' => $this->tokens->seal(SealedTokens::REFRESH, [
                'user' => $account->name,
                'client_id' => $clientId,
            ], config('connector.refresh_token_ttl')),
            'scope' => $scope,
        ], 200, ['Cache-Control' => 'no-store']);
    }

    private function error(string $code): JsonResponse
    {
        return response()->json(['error' => $code], 400, ['Cache-Control' => 'no-store']);
    }
}
