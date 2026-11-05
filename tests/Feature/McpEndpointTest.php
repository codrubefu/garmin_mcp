<?php

namespace Tests\Feature;

use App\OAuth\SealedTokens;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class McpEndpointTest extends TestCase
{
    private function rpc(string $method, array $params = [], array $headers = [])
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers);
    }

    private function accessToken(array $claims = ['aud' => 'https://garmin.test/mcp'], int $ttl = 3600, string $type = SealedTokens::ACCESS): string
    {
        return app(SealedTokens::class)->seal($type, $claims, $ttl);
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_without_a_token_it_answers_401_with_the_metadata_pointer(): void
    {
        $response = $this->rpc('initialize')->assertUnauthorized();

        $this->assertStringContainsString(
            'resource_metadata="https://garmin.test/.well-known/oauth-protected-resource/mcp"',
            $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_bad_tokens_are_rejected(): void
    {
        $this->rpc('initialize', [], $this->bearer('garbage'))->assertUnauthorized();
        $this->rpc('initialize', [], $this->bearer($this->accessToken(['aud' => 'https://other.example/mcp'])))->assertUnauthorized();
        $this->rpc('initialize', [], $this->bearer($this->accessToken(ttl: -1)))->assertUnauthorized();
        $this->rpc('initialize', [], $this->bearer($this->accessToken(type: SealedTokens::REFRESH)))->assertUnauthorized();
    }

    public function test_a_token_from_another_app_key_is_rejected(): void
    {
        $token = $this->accessToken();

        Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $this->app->forgetInstance('encrypter');
        \Illuminate\Support\Facades\Crypt::clearResolvedInstance('encrypter');

        $this->rpc('initialize', [], $this->bearer($token))->assertUnauthorized();
    }

    public function test_a_valid_token_reaches_the_server(): void
    {
        $response = $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1'],
        ], $this->bearer($this->accessToken()));

        $response->assertOk()->assertJsonPath('result.serverInfo.name', 'Garmin Connect');
    }

    public function test_tools_are_listed_as_read_only(): void
    {
        $tools = $this->rpc('tools/list', [], $this->bearer($this->accessToken()))
            ->assertOk()
            ->json('result.tools');

        $this->assertCount(18, $tools);

        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint'], $tool['name']);
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $tool['name']);
        }
    }
}
