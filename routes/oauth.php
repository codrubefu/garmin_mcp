<?php

use App\Http\Controllers\OAuth\AuthorizeController;
use App\Http\Controllers\OAuth\MetadataController;
use App\Http\Controllers\OAuth\RegisterController;
use App\Http\Controllers\OAuth\TokenController;
use App\Http\Middleware\SecureLoginPage;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/oauth-protected-resource', [MetadataController::class, 'protectedResource']);

// Laravel MCP's 401 middleware looks for this route name to build resource_metadata.
Route::get('/.well-known/oauth-protected-resource/{path}', [MetadataController::class, 'protectedResource'])
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');

Route::get('/.well-known/oauth-authorization-server', [MetadataController::class, 'authorizationServer']);

Route::post('/oauth/register', RegisterController::class)
    ->middleware('throttle:oauth')
    ->name('oauth.register');

Route::post('/oauth/token', TokenController::class)
    ->middleware('throttle:oauth')
    ->name('oauth.token');

Route::middleware(['web', SecureLoginPage::class])->group(function () {
    Route::get('/oauth/authorize', [AuthorizeController::class, 'show'])->name('oauth.authorize');
    Route::post('/oauth/authorize', [AuthorizeController::class, 'store'])->middleware('throttle:oauth-login');
});
