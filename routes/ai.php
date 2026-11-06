<?php

use App\Accounts\Account;
use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\GarminServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/{user}', GarminServer::class)
    ->where('user', Account::NAME_PATTERN)
    ->middleware([AuthenticateMcpToken::class, 'throttle:mcp']);
