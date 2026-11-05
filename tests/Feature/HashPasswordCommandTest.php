<?php

namespace Tests\Feature;

use Tests\TestCase;

class HashPasswordCommandTest extends TestCase
{
    public function test_it_accepts_a_password_with_eight_characters(): void
    {
        $this->artisan('mcp:hash-password')
            ->expectsQuestion('Password for the connector login (at least 8 characters)', '12345678')
            ->assertSuccessful();
    }

    public function test_it_rejects_a_password_with_seven_characters(): void
    {
        $this->artisan('mcp:hash-password')
            ->expectsQuestion('Password for the connector login (at least 8 characters)', '1234567')
            ->expectsOutput('The password must have at least 8 characters.')
            ->assertFailed();
    }
}
