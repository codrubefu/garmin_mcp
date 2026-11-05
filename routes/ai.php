<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\GarminServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', GarminServer::class)
    ->middleware([AuthenticateMcpToken::class, 'throttle:mcp']);
