<?php

namespace App\Garmin;

use App\Accounts\Account;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GarminClient
{
    private const KEY_URL = 'https://connect.garmin.com/app/settings/personalApiToken';

    /** Reuse a token only while it has more than this many seconds left. */
    private const REFRESH_WINDOW = 3600;

    public function __construct(private Account $account) {}

    /**
     * @param  array<string, scalar>  $query
     */
    public function get(string $path, array $query = []): mixed
    {
        $response = $this->send($path, $query);

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
            $response = $this->send($path, $query);
        }

        if ($response->failed()) {
            throw $this->errorFor($response->status());
        }

        return $response->body() === '' ? null : $response->json();
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function send(string $path, array $query): Response
    {
        $token = $this->token();

        try {
            return $this->http()
                ->withToken($token)
                ->acceptJson()
                ->get($path, $query);
        } catch (ConnectionException) {
            throw new GarminException('Could not reach Garmin, or the request timed out. Try again shortly.');
        }
    }

    private function token(): string
    {
        $key = $this->cacheKey();

        try {
            return Cache::get($key) ?? Cache::lock($key.':lock', 15)->block(
                15,
                fn (): string => Cache::get($key) ?? $this->requestToken(),
            );
        } catch (LockTimeoutException) {
            throw new GarminException('Timed out waiting for a Garmin access token. Try again shortly.');
        }
    }

    private function requestToken(): string
    {
        try {
            $response = $this->http()
                ->withHeaders(['Authorization' => 'ApiKey '.$this->apiKey(), 'Accept' => '*/*'])
                ->post(config('garmin.token_path'));
        } catch (ConnectionException) {
            throw new GarminException('Could not reach Garmin to get an access token. Try again shortly.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new GarminException(
                'Garmin rejected the Personal API key, which has probably expired. Create a new key at '
                .self::KEY_URL.' and update '.$this->apiKeyVariable().' on the server.',
                $response->status(),
            );
        }

        if ($response->failed()) {
            throw $this->errorFor($response->status());
        }

        $token = trim($response->body());
        $ttl = $this->expiry($token) - time() - self::REFRESH_WINDOW;

        if ($ttl > 0) {
            Cache::put($this->cacheKey(), $token, $ttl);
        }

        return $token;
    }

    private function expiry(string $jwt): int
    {
        $payload = explode('.', $jwt)[1] ?? '';
        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);

        if (! is_int($claims['exp'] ?? null)) {
            throw new GarminException('Garmin returned an access token in an unexpected format.');
        }

        return $claims['exp'];
    }

    private function errorFor(int $status): GarminException
    {
        return new GarminException(match (true) {
            $status === 401 => 'Garmin rejected the access token even after renewing it.',
            $status === 403 => 'Garmin denied access to this data.',
            $status === 404 => 'Garmin found nothing for this request.',
            $status === 429 => 'Garmin rate limit reached. Wait a few minutes before trying again.',
            $status >= 500 => "Garmin is having problems (HTTP {$status}). Try again later.",
            default => "Garmin rejected the request (HTTP {$status}).",
        }, $status);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('garmin.base_url'))->timeout(config('garmin.timeout'));
    }

    private function apiKey(): string
    {
        return (string) $this->account->garminApiKey
            ?: throw new GarminException($this->apiKeyVariable().' is not set on the server.');
    }

    private function apiKeyVariable(): string
    {
        return Account::envName('GARMIN_API_KEY', $this->account->name);
    }

    /** Tied to the key, so replacing the key stops the old token being used. */
    private function cacheKey(): string
    {
        return 'garmin:token:'.hash('sha256', $this->apiKey());
    }
}
