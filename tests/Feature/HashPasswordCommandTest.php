<?php

namespace Tests\Feature;

use Tests\TestCase;

class HashPasswordCommandTest extends TestCase
{
    public function test_it_accepts_a_password_with_eight_characters(): void
    {
        $this->artisan('mcp:hash-password', ['user' => 'vio'])
            ->expectsQuestion('Password for the connector login (at least 8 characters)', '12345678')
            ->expectsOutputToContain("MCP_PASSWORD_HASH_VIO='")
            ->assertSuccessful();
    }

    public function test_it_rejects_a_password_with_seven_characters(): void
    {
        $this->artisan('mcp:hash-password', ['user' => 'vio'])
            ->expectsQuestion('Password for the connector login (at least 8 characters)', '1234567')
            ->expectsOutput('The password must have at least 8 characters.')
            ->assertFailed();
    }

    public function test_it_rejects_a_user_name_that_cannot_be_an_environment_variable(): void
    {
        $this->artisan('mcp:hash-password', ['user' => 'Vio-2'])
            ->expectsOutput('The user name may only contain lowercase letters, digits and underscores.')
            ->assertFailed();
    }
}
