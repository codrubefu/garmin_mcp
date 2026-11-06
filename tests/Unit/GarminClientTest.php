<?php

namespace Tests\Unit;

use App\Accounts\Account;
use App\Garmin\GarminClient;
use App\Garmin\GarminException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GarminClientTest extends TestCase
{
    private const TOKEN_URL = 'garmin.test/personalapitoken-service/*';

    private const DATA_URL = 'garmin.test/data';

    protected function setUp(): void
    {
        parent::setUp();

        config(['garmin.base_url' => 'https://garmin.test']);
        $this->useAccount('secret-key');
    }

    private function useAccount(?string $apiKey): void
    {
        $this->app->instance(Account::class, new Account('codrut', null, $apiKey));
    }

    private function jwt(int $expiresIn = 86400): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + $expiresIn])), '+/', '-_'), '=');

        return "header.{$payload}.signature";
    }

    public function test_it_exchanges_the_key_and_sends_the_bearer_token(): void
    {
        $jwt = $this->jwt();

        Http::fake([
            self::TOKEN_URL => Http::response($jwt."\n"),
            self::DATA_URL.'*' => Http::response(['ok' => true]),
        ]);

        $result = app(GarminClient::class)->get('/data', ['date' => '2026-11-01']);

        $this->assertSame(['ok' => true], $result);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/personalapitoken-service/')
            && $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'ApiKey secret-key'));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://garmin.test/data?date=2026-11-01'
            && $r->hasHeader('Authorization', "Bearer {$jwt}"));
    }

    public function test_it_reuses_the_cached_token(): void
    {
        Http::fake([self::TOKEN_URL => Http::response($this->jwt()), self::DATA_URL => Http::response([])]);

        app(GarminClient::class)->get('/data');
        app(GarminClient::class)->get('/data');

        Http::assertSentCount(3);
    }

    public function test_a_token_close_to_expiry_is_not_cached(): void
    {
        Http::fake([self::TOKEN_URL => Http::response($this->jwt(600)), self::DATA_URL => Http::response([])]);

        app(GarminClient::class)->get('/data');
        app(GarminClient::class)->get('/data');

        Http::assertSentCount(4);
    }

    public function test_changing_the_key_stops_the_old_token_being_used(): void
    {
        Http::fake([self::TOKEN_URL => Http::response($this->jwt()), self::DATA_URL => Http::response([])]);

        app(GarminClient::class)->get('/data');
        $this->useAccount('new-key');
        app(GarminClient::class)->get('/data');

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'ApiKey new-key'));
    }

    public function test_a_401_renews_the_token_and_retries_once(): void
    {
        $old = $this->jwt();
        $new = $this->jwt(90000);

        Http::fake([
            self::TOKEN_URL => Http::sequence()->push($old)->push($new),
            self::DATA_URL => Http::sequence()->push('', 401)->push(['ok' => true]),
        ]);

        $this->assertSame(['ok' => true], app(GarminClient::class)->get('/data'));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', "Bearer {$new}"));
    }

    public function test_a_second_401_is_reported(): void
    {
        Http::fake([self::TOKEN_URL => Http::response($this->jwt()), self::DATA_URL => Http::response('', 401)]);

        $this->expectException(GarminException::class);

        try {
            app(GarminClient::class)->get('/data');
        } finally {
            Http::assertSentCount(4);
        }
    }

    public function test_an_expired_key_explains_what_to_do(): void
    {
        Http::fake([self::TOKEN_URL => Http::response('', 401)]);

        $this->expectExceptionMessage('Create a new key');

        app(GarminClient::class)->get('/data');
    }

    public function test_a_missing_key_is_reported(): void
    {
        $this->useAccount(null);

        $this->expectExceptionMessage('GARMIN_API_KEY_CODRUT is not set');

        app(GarminClient::class)->get('/data');
    }

    public function test_a_token_that_is_not_a_jwt_is_rejected(): void
    {
        Http::fake([self::TOKEN_URL => Http::response('not-a-jwt')]);

        $this->expectExceptionMessage('unexpected format');

        app(GarminClient::class)->get('/data');
    }

    #[DataProvider('errors')]
    public function test_http_errors_become_clear_messages(int $status, string $message): void
    {
        Http::fake([self::TOKEN_URL => Http::response($this->jwt()), self::DATA_URL => Http::response('body with secrets', $status)]);

        try {
            app(GarminClient::class)->get('/data');
            $this->fail('Expected an exception.');
        } catch (GarminException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
            $this->assertStringNotContainsString('secrets', $e->getMessage());
            $this->assertSame($status, $e->status);
        }
    }

    public static function errors(): array
    {
        return [[403, 'denied'], [404, 'nothing'], [429, 'rate limit'], [503, 'HTTP 503'], [400, 'HTTP 400']];
    }

    public function test_timeouts_become_clear_messages(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response($this->jwt()),
            self::DATA_URL => fn () => throw new ConnectionException('timed out'),
        ]);

        $this->expectExceptionMessage('timed out');

        app(GarminClient::class)->get('/data');
    }

    public function test_an_empty_body_returns_null(): void
    {
        Http::fake([self::TOKEN_URL => Http::response($this->jwt()), self::DATA_URL => Http::response('', 204)]);

        $this->assertNull(app(GarminClient::class)->get('/data'));
    }
}
