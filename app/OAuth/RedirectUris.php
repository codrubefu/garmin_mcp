<?php

namespace App\OAuth;

/** There are no stored clients, so every redirect is checked against this allowlist. */
class RedirectUris
{
    public static function allows(mixed $uri): bool
    {
        if (! is_string($uri)) {
            return false;
        }

        if (in_array($uri, config('connector.redirect_uris'), true)) {
            return true;
        }

        $parts = parse_url($uri);

        // Any port is allowed here because Claude Code picks a new one per session.
        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'http'
            && in_array($parts['host'] ?? null, config('connector.loopback_hosts'), true)
            && ($parts['path'] ?? null) === config('connector.loopback_path')
            && array_diff(array_keys($parts), ['scheme', 'host', 'port', 'path']) === [];
    }
}
