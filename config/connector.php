<?php

return [
    'password_hash' => env('MCP_OWNER_PASSWORD_HASH'),

    'scope' => 'mcp:use',

    // Claude on the web, Desktop, mobile and Cowork. Claude Code uses a loopback redirect instead.
    'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    'loopback_hosts' => ['localhost', '127.0.0.1'],
    'loopback_path' => '/callback',

    'code_ttl' => 60,
    'access_token_ttl' => 3600,
    'refresh_token_ttl' => 60 * 60 * 24 * 90,
];
