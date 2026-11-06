<?php

namespace Tests\Feature;

use App\OAuth\SealedTokens;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OAuthFlowTest extends TestCase
{
    private const REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

    private const VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk0';

    private const PASSWORD = 'correct horse battery staple';

    private const OTHER_PASSWORD = 'vio has another password';

    protected function setUp(): void
    {
        parent::setUp();

        config(['connector.accounts' => [
            'codrut' => ['password_hash' => Hash::make(self::PASSWORD), 'garmin_api_key' => null],
            'vio' => ['password_hash' => Hash::make(self::OTHER_PASSWORD), 'garmin_api_key' => null],
        ]]);
    }

    private function challenge(string $verifier = self::VERIFIER): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** @return array<string, string> */
    private function authorizeParams(array $overrides = []): array
    {
        return [
            'client_id' => 'client-1',
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'state' => 'state-1',
            'code_challenge' => $this->challenge(),
            'code_challenge_method' => 'S256',
            'resource' => 'https://garmin.test/mcp/codrut',
            ...$overrides,
        ];
    }

    private function login(array $overrides = [], string $password = self::PASSWORD): TestResponse
    {
        return $this->withoutMiddleware(PreventRequestForgery::class)
            ->post('/oauth/authorize', [...$this->authorizeParams($overrides), 'password' => $password]);
    }

    private function code(array $overrides = []): string
    {
        parse_str((string) parse_url($this->login($overrides)->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['code'];
    }

    private function exchange(string $code, array $overrides = []): TestResponse
    {
        return $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => 'client-1',
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => self::VERIFIER,
            ...$overrides,
        ]);
    }

    private function refresh(string $token, array $overrides = []): TestResponse
    {
        return $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $token, 'client_id' => 'client-1', ...$overrides]);
    }

    public function test_metadata_points_at_this_server(): void
    {
        $this->get('/.well-known/oauth-protected-resource/mcp/codrut')
            ->assertOk()
            ->assertJson(['resource' => 'https://garmin.test/mcp/codrut', 'authorization_servers' => ['https://garmin.test']]);

        $this->get('/.well-known/oauth-protected-resource/mcp/vio')->assertJsonPath('resource', 'https://garmin.test/mcp/vio');

        $this->get('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJson([
                'issuer' => 'https://garmin.test',
                'authorization_endpoint' => 'https://garmin.test/oauth/authorize',
                'token_endpoint' => 'https://garmin.test/oauth/token',
                'registration_endpoint' => 'https://garmin.test/oauth/register',
                'code_challenge_methods_supported' => ['S256'],
                'token_endpoint_auth_methods_supported' => ['none'],
                'scopes_supported' => ['mcp:use', 'offline_access'],
            ]);
    }

    public function test_metadata_is_not_found_for_unknown_users(): void
    {
        $this->get('/.well-known/oauth-protected-resource/mcp/ana')->assertNotFound();
        $this->get('/.well-known/oauth-protected-resource/mcp')->assertNotFound();
        $this->get('/.well-known/oauth-protected-resource')->assertNotFound();
    }

    public function test_registration_accepts_claude_and_rejects_others(): void
    {
        $this->postJson('/oauth/register', ['redirect_uris' => [self::REDIRECT, 'http://localhost:3118/callback']])
            ->assertCreated()
            ->assertJsonStructure(['client_id'])
            ->assertJsonPath('token_endpoint_auth_method', 'none');

        $this->postJson('/oauth/register', ['redirect_uris' => [self::REDIRECT, 'https://evil.example/callback']])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $this->postJson('/oauth/register', [])->assertStatus(400);
    }

    #[DataProvider('redirects')]
    public function test_redirect_allowlist(string $uri, bool $allowed): void
    {
        $response = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams(['redirect_uri' => $uri])));

        $response->assertStatus($allowed ? 200 : 400);
    }

    public static function redirects(): array
    {
        return [
            'claude' => [self::REDIRECT, true],
            'localhost, any port' => ['http://localhost:3118/callback', true],
            'loopback ip, any port' => ['http://127.0.0.1:50211/callback', true],
            'loopback without port' => ['http://localhost/callback', true],
            'other host' => ['https://evil.example/api/mcp/auth_callback', false],
            'similar host' => ['https://claude.ai.evil.example/api/mcp/auth_callback', false],
            'loopback lookalike' => ['http://localhost.evil.example/callback', false],
            'userinfo trick' => ['http://localhost@evil.example/callback', false],
            'userinfo on loopback' => ['http://evil@localhost/callback', false],
            'other path' => ['http://localhost:3118/other', false],
            'https loopback' => ['https://localhost/callback', false],
            'loopback with query' => ['http://localhost/callback?x=1', false],
            'claude with extra path' => [self::REDIRECT.'/x', false],
        ];
    }

    public function test_login_page_is_protected_and_shows_the_redirect_host(): void
    {
        $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams()))
            ->assertOk()
            ->assertSee('claude.ai')
            ->assertSee('codrut')
            ->assertDontSee('name="username"', false)
            ->assertSee('name="_token"', false)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_login_requires_a_csrf_token(): void
    {
        // Laravel skips this check in the testing environment.
        $this->app['env'] = 'local';

        $this->post('/oauth/authorize', [...$this->authorizeParams(), 'password' => self::PASSWORD])
            ->assertStatus(419);
    }

    public function test_requests_without_s256_pkce_go_back_with_an_error(): void
    {
        foreach ([['code_challenge_method' => 'plain'], ['code_challenge' => ''], ['code_challenge' => 'short']] as $bad) {
            $location = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($bad)))
                ->assertRedirect()
                ->headers->get('Location');

            $this->assertStringStartsWith(self::REDIRECT.'?error=invalid_request', $location);
            $this->assertStringContainsString('state=state-1', $location);
        }
    }

    public function test_a_wrong_resource_is_rejected(): void
    {
        $location = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams(['resource' => 'https://other.example/mcp'])))
            ->headers->get('Location');

        $this->assertStringContainsString('error=invalid_target', $location);
    }

    public function test_a_wrong_password_shows_an_error_and_issues_no_code(): void
    {
        $this->login(password: 'wrong')->assertStatus(422)->assertSee('Wrong password');
    }

    public function test_login_fails_when_no_password_is_configured(): void
    {
        config(['connector.accounts.codrut.password_hash' => '']);

        $this->login(password: '')->assertStatus(422);
    }

    public function test_another_users_password_does_not_open_this_account(): void
    {
        $this->login(password: self::OTHER_PASSWORD)->assertStatus(422);
        $this->login(['resource' => 'https://garmin.test/mcp/vio'], self::OTHER_PASSWORD)->assertRedirect();
    }

    public function test_an_unknown_user_is_rejected(): void
    {
        $location = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams(['resource' => 'https://garmin.test/mcp/ana'])))
            ->headers->get('Location');

        $this->assertStringContainsString('error=invalid_target', $location);
    }

    public function test_without_a_resource_the_form_asks_for_the_user_name(): void
    {
        $params = $this->authorizeParams();
        unset($params['resource']);

        $this->get('/oauth/authorize?'.http_build_query($params))->assertOk()->assertSee('name="username"', false);

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post('/oauth/authorize', [...$params, 'username' => 'ana', 'password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertSee('Wrong user name or password');

        $location = $this->withoutMiddleware(PreventRequestForgery::class)
            ->post('/oauth/authorize', [...$params, 'username' => 'vio', 'password' => self::OTHER_PASSWORD])
            ->assertRedirect()
            ->headers->get('Location');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $token = $this->exchange($query['code'])->assertOk()->json('access_token');

        $this->assertSame('https://garmin.test/mcp/vio', app(SealedTokens::class)->open($token, SealedTokens::ACCESS)['aud']);
    }

    public function test_tokens_are_issued_for_the_user_who_logged_in(): void
    {
        $tokens = $this->exchange($this->code())->json();
        $refreshed = $this->refresh($tokens['refresh_token'])->json('access_token');

        foreach ([$tokens['access_token'], $refreshed] as $token) {
            $this->assertSame('https://garmin.test/mcp/codrut', app(SealedTokens::class)->open($token, SealedTokens::ACCESS)['aud']);
        }

        $this->exchange($this->code(), ['resource' => 'https://garmin.test/mcp/vio'])->assertStatus(400);
    }

    public function test_a_removed_user_cannot_refresh(): void
    {
        $token = $this->exchange($this->code())->json('refresh_token');

        config(['connector.accounts' => []]);

        $this->refresh($token)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_login_attempts_are_throttled(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->login(password: 'wrong')->assertStatus(422);
        }

        $this->login()->assertStatus(429);
    }

    public function test_full_flow_issues_tokens(): void
    {
        $location = $this->login()->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(self::REDIRECT.'?code=', $location);
        $this->assertStringContainsString('state=state-1', $location);
        $this->assertStringContainsString('iss=https%3A%2F%2Fgarmin.test', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->exchange($query['code'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'scope'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_the_token_endpoint_accepts_a_loopback_flow(): void
    {
        $uri = 'http://127.0.0.1:50211/callback';

        $this->exchange($this->code(['redirect_uri' => $uri]), ['redirect_uri' => $uri])->assertOk();
    }

    public function test_a_wrong_verifier_is_rejected_and_does_not_burn_the_code(): void
    {
        $code = $this->code();

        $this->exchange($code, ['code_verifier' => str_repeat('a', 43)])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $this->exchange($code, ['code_verifier' => ''])->assertStatus(400);
        $this->exchange($code)->assertOk();
    }

    public function test_a_code_only_works_once(): void
    {
        $code = $this->code();

        $this->exchange($code)->assertOk();
        $this->exchange($code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $code = $this->code();

        $this->travel(61)->seconds();

        $this->exchange($code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_a_code_is_bound_to_its_client_and_redirect(): void
    {
        $code = $this->code();

        $this->exchange($code, ['client_id' => 'other'])->assertStatus(400);
        $this->exchange($code, ['redirect_uri' => 'http://localhost/callback'])->assertStatus(400);
        $this->exchange($code, ['resource' => 'https://other.example/mcp'])->assertStatus(400);
    }

    public function test_refresh_rotates_and_a_used_refresh_token_is_rejected(): void
    {
        $first = $this->exchange($this->code())->json('refresh_token');

        $second = $this->refresh($first)->assertOk()->json('refresh_token');

        $this->assertNotSame($first, $second);
        $this->refresh($first)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $this->refresh($second)->assertOk();
    }

    public function test_an_expired_refresh_token_is_rejected(): void
    {
        $token = $this->exchange($this->code())->json('refresh_token');

        $this->travel(91)->days();

        $this->refresh($token)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_a_refresh_token_is_bound_to_its_client(): void
    {
        $token = $this->exchange($this->code())->json('refresh_token');

        $this->refresh($token, ['client_id' => 'other'])->assertStatus(400);
    }

    public function test_token_types_cannot_be_swapped(): void
    {
        $tokens = $this->exchange($this->code())->json();

        $this->refresh($tokens['access_token'])->assertStatus(400);
        $this->exchange($tokens['refresh_token'])->assertStatus(400);
        $this->assertNull(app(SealedTokens::class)->open($tokens['refresh_token'], SealedTokens::ACCESS));
    }

    public function test_a_tampered_or_garbage_token_is_rejected(): void
    {
        $tokens = app(SealedTokens::class);
        $sealed = $tokens->seal(SealedTokens::ACCESS, [], 60);

        $this->assertNotNull($tokens->open($sealed, SealedTokens::ACCESS));
        $this->assertNull($tokens->open(substr($sealed, 0, -4).'AAAA', SealedTokens::ACCESS));
        $this->assertNull($tokens->open('garbage', SealedTokens::ACCESS));
        $this->assertNull($tokens->open(null, SealedTokens::ACCESS));
    }

    public function test_an_unknown_grant_is_rejected(): void
    {
        $this->post('/oauth/token', ['grant_type' => 'client_credentials'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'unsupported_grant_type');
    }
}
