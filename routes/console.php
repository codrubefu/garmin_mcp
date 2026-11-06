<?php

use App\Accounts\Account;
use App\Garmin\GarminClient;
use App\Garmin\GarminException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('mcp:hash-password {user}', function (string $user) {
    if (preg_match('/^'.Account::NAME_PATTERN.'$/D', $user) !== 1) {
        $this->error('The user name may only contain lowercase letters, digits and underscores.');

        return 1;
    }

    $password = (string) $this->secret('Password for the connector login (at least 8 characters)');

    if (mb_strlen($password) < 8) {
        $this->error('The password must have at least 8 characters.');

        return 1;
    }

    // Single quotes stop Docker Compose and dotenv from expanding the $ signs in the hash.
    $this->line(Account::envName('MCP_PASSWORD_HASH', $user)."='".Hash::make($password)."'");
})->purpose('Generate the password hash of one user for the .env file');

Artisan::command('garmin:check {user}', function (string $user) {
    $account = Account::find($user);

    if ($account === null) {
        $this->error("Unknown user \"{$user}\". Add it to MCP_USERS in the .env file.");

        return 1;
    }

    try {
        $settings = app(GarminClient::class, ['account' => $account])->get('/userprofile-service/userprofile/settings');
    } catch (GarminException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->info("Garmin connection works for {$user}. Profile fields returned: ".count((array) $settings));
})->purpose('Check the Garmin API key of one user with a real request');
