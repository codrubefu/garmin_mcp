<?php

use App\Accounts\Account;

// MCP_USERS lists the accounts, for example "codrut,vio". Each one is served at /mcp/{name}
// and reads MCP_PASSWORD_HASH_{NAME} and GARMIN_API_KEY_{NAME}.
$accountNames = array_filter(
    array_map(fn (string $name): string => strtolower(trim($name)), explode(',', (string) env('MCP_USERS', ''))),
    fn (string $name): bool => preg_match('/^'.Account::NAME_PATTERN.'$/D', $name) === 1,
);

return [
    'accounts' => array_combine($accountNames, array_map(fn (string $name): array => [
        'password_hash' => env(Account::envName('MCP_PASSWORD_HASH', $name)),
        'garmin_api_key' => env(Account::envName('GARMIN_API_KEY', $name)),
    ], $accountNames)),

    'scope' => 'mcp:use',

    // Claude on the web, Desktop, mobile and Cowork. Claude Code uses a loopback redirect instead.
    'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    'loopback_hosts' => ['localhost', '127.0.0.1'],
    'loopback_path' => '/callback',

    'code_ttl' => 60,
    'access_token_ttl' => 3600,
    'refresh_token_ttl' => 60 * 60 * 24 * 90,
];
