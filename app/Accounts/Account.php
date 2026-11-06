<?php

namespace App\Accounts;

/**
 * One person who can connect Claude to their own Garmin data, served at /mcp/{name}.
 * Accounts live in the environment, so there is still no database.
 */
final readonly class Account
{
    public const NAME_PATTERN = '[a-z0-9_]+';

    public function __construct(
        public string $name,
        public ?string $passwordHash,
        public ?string $garminApiKey,
    ) {}

    public static function find(mixed $name): ?self
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        $settings = config('connector.accounts')[$name] ?? null;

        if (! is_array($settings)) {
            return null;
        }

        return new self($name, $settings['password_hash'] ?? null, $settings['garmin_api_key'] ?? null);
    }

    /** Finds the account whose MCP endpoint is exactly this URL. */
    public static function fromResource(mixed $resource): ?self
    {
        $prefix = url('/mcp').'/';

        if (! is_string($resource) || ! str_starts_with($resource, $prefix)) {
            return null;
        }

        return self::find(substr($resource, strlen($prefix)));
    }

    /** Builds the environment variable name for one of an account's settings, such as GARMIN_API_KEY_CODRUT. */
    public static function envName(string $prefix, string $name): string
    {
        return $prefix.'_'.strtoupper($name);
    }

    public function resourceUrl(): string
    {
        return url('/mcp/'.$this->name);
    }
}
