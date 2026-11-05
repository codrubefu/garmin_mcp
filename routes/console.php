<?php

use App\Garmin\GarminClient;
use App\Garmin\GarminException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('mcp:hash-password', function () {
    $password = (string) $this->secret('Password for the connector login (at least 8 characters)');

    if (mb_strlen($password) < 8) {
        $this->error('The password must have at least 8 characters.');

        return 1;
    }

    // Single quotes stop Docker Compose and dotenv from expanding the $ signs in the hash.
    $this->line("MCP_OWNER_PASSWORD_HASH='".Hash::make($password)."'");
})->purpose('Generate the password hash for the .env file');

Artisan::command('garmin:check', function (GarminClient $garmin) {
    try {
        $settings = $garmin->get('/userprofile-service/userprofile/settings');
    } catch (GarminException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->info('Garmin connection works. Profile fields returned: '.count((array) $settings));
})->purpose('Check the Garmin API key with a real request');
