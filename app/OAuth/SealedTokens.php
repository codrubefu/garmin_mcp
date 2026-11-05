<?php

namespace App\OAuth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Codes and tokens are encrypted claims instead of database rows. The only
 * server-side state is the file cache entry that marks one-time values as used.
 */
class SealedTokens
{
    public const CODE = 'code';

    public const ACCESS = 'access';

    public const REFRESH = 'refresh';

    /**
     * @param  array<string, mixed>  $claims
     */
    public function seal(string $type, array $claims, int $ttl): string
    {
        $payload = json_encode([
            ...$claims,
            'typ' => $type,
            'jti' => (string) Str::uuid(),
            'exp' => now()->timestamp + $ttl,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(Crypt::encryptString($payload), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null The claims, or null unless the token is valid and of this type.
     */
    public function open(?string $token, string $type): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $claims = json_decode(Crypt::decryptString(strtr($token, '-_', '+/')), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($claims) || ($claims['typ'] ?? null) !== $type || ($claims['exp'] ?? 0) <= now()->timestamp) {
            return null;
        }

        return $claims;
    }

    /**
     * Marks a one-time token as used. Returns false if it had been used before.
     *
     * @param  array<string, mixed>  $claims
     */
    public function consume(array $claims): bool
    {
        return Cache::add('oauth:used:'.$claims['jti'], true, $claims['exp'] - now()->timestamp + 60);
    }
}
