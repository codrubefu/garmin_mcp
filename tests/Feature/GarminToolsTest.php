<?php

namespace Tests\Feature;

use App\Accounts\Account;
use App\Mcp\Servers\GarminServer;
use App\Mcp\Tools;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GarminToolsTest extends TestCase
{
    private const DAY = ['date' => '2026-11-01'];

    private const RANGE = ['start_date' => '2026-11-01', 'end_date' => '2026-11-07'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['garmin.base_url' => 'https://garmin.test']);
        $this->app->instance(Account::class, new Account('codrut', null, 'key'));
        Cache::put('garmin:token:'.hash('sha256', 'key'), 'jwt', 3600);
    }

    #[DataProvider('tools')]
    public function test_each_tool_calls_the_right_endpoint(string $tool, array $arguments, string $url): void
    {
        Http::fake(['*' => Http::response(['value' => 'from-garmin'])]);

        GarminServer::tool($tool, $arguments)->assertOk()->assertSee('from-garmin');

        Http::assertSent(fn (Request $r) => urldecode($r->url()) === "https://garmin.test{$url}"
            && $r->hasHeader('Authorization', 'Bearer jwt'));
    }

    public static function tools(): array
    {
        return [
            'sleep day' => [Tools\GetSleep::class, self::DAY, '/sleep-service/sleep/dailySleep?date=2026-11-01'],
            'sleep range' => [Tools\GetSleep::class, self::RANGE, '/sleep-service/sleep/dailySleepSummariesByDate?startDate=2026-11-01&endDate=2026-11-07'],
            'hrv day' => [Tools\GetHrv::class, self::DAY, '/hrv-service/hrv/2026-11-01'],
            'hrv range' => [Tools\GetHrv::class, self::RANGE, '/hrv-service/hrv/daily/2026-11-01/2026-11-07'],
            'body battery' => [Tools\GetBodyBattery::class, self::RANGE, '/wellness-service/wellness/bodyBattery/reports/daily?startDate=2026-11-01&endDate=2026-11-07'],
            'stress' => [Tools\GetStress::class, self::RANGE, '/usersummary-service/stats/stress/daily/2026-11-01/2026-11-07'],
            'heart rate' => [Tools\GetHeartRate::class, self::DAY, '/wellness-service/wellness/dailyHeartRate?date=2026-11-01'],
            'spo2' => [Tools\GetSpo2::class, self::DAY, '/wellness-service/wellness/daily/spo2acclimation/2026-11-01'],
            'summaries' => [Tools\GetDailySummaries::class, self::RANGE, '/usersummary-service/v2/dailysummaries?startDate=2026-11-01&endDate=2026-11-07'],
            'steps' => [Tools\GetSteps::class, self::RANGE, '/usersummary-service/stats/steps/daily/2026-11-01/2026-11-07'],
            'hydration' => [Tools\GetHydration::class, self::RANGE, '/usersummary-service/stats/hydration/daily/2026-11-01/2026-11-07'],
            'wellness activities' => [Tools\GetWellnessActivities::class, self::RANGE, '/wellnessactivity-service/activity/summary/range/2026-11-01/2026-11-07'],
            'fitness age' => [Tools\GetFitnessAge::class, self::DAY, '/fitnessage-service/fitnessage/v2/2026-11-01'],
            'zones' => [Tools\GetHeartRateZones::class, [], '/biometric-service/heartRateZones/v2'],
            'weight' => [Tools\GetWeight::class, self::RANGE, '/weight-service/weight/range/2026-11-01/2026-11-07?includeAll=true'],
            'weight goals' => [Tools\GetWeightGoals::class, self::RANGE, '/goal-service/goal/user/effective/weightgoal/2026-11-01/2026-11-07'],
            'food' => [Tools\GetFoodLog::class, self::DAY, '/nutrition-service/food/logs/2026-11-01'],
            'profile' => [Tools\GetProfileSettings::class, [], '/userprofile-service/userprofile/settings'],
            'activities default' => [Tools\SearchActivities::class, [], '/activitylist-service/activities/search/activities?start=0&limit=20'],
            'activities filtered' => [
                Tools\SearchActivities::class,
                [...self::RANGE, 'activity_type' => 'running', 'start' => 20, 'limit' => 5],
                '/activitylist-service/activities/search/activities?start=20&limit=5&startDate=2026-11-01&endDate=2026-11-07&activityType=running',
            ],
        ];
    }

    public function test_get_activity_combines_the_activity_and_workouts(): void
    {
        Http::fake([
            '*/activity-service/activity/42' => Http::response(['name' => 'Morning Run']),
            '*/activity-service/activity/42/workouts' => Http::response([['name' => 'Intervals']]),
        ]);

        GarminServer::tool(Tools\GetActivity::class, ['activity_id' => 42])
            ->assertOk()
            ->assertSee(['Morning Run', 'Intervals']);
    }

    public function test_get_activity_works_when_no_workout_is_linked(): void
    {
        Http::fake([
            '*/activity-service/activity/42' => Http::response(['name' => 'Morning Run']),
            '*/activity-service/activity/42/workouts' => Http::response('', 404),
        ]);

        GarminServer::tool(Tools\GetActivity::class, ['activity_id' => 42])->assertOk()->assertSee('Morning Run');
    }

    public function test_get_activity_reports_a_missing_activity(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        GarminServer::tool(Tools\GetActivity::class, ['activity_id' => 42])->assertHasErrors(['found nothing']);
    }

    public function test_get_activity_rejects_a_path_injection(): void
    {
        Http::fake();

        GarminServer::tool(Tools\GetActivity::class, ['activity_id' => '42/../../x'])->assertHasErrors();

        Http::assertNothingSent();
    }

    #[DataProvider('invalidArguments')]
    public function test_invalid_arguments_never_reach_garmin(string $tool, array $arguments): void
    {
        Http::fake();

        GarminServer::tool($tool, $arguments)->assertHasErrors();

        Http::assertNothingSent();
    }

    public static function invalidArguments(): array
    {
        return [
            'missing date' => [Tools\GetFoodLog::class, []],
            'bad date' => [Tools\GetFoodLog::class, ['date' => '01-11-2026']],
            'path in date' => [Tools\GetFoodLog::class, ['date' => '../../x']],
            'impossible date' => [Tools\GetHeartRate::class, ['date' => '2026-02-31']],
            'missing end' => [Tools\GetSteps::class, ['start_date' => '2026-11-01']],
            'reversed' => [Tools\GetSteps::class, ['start_date' => '2026-11-07', 'end_date' => '2026-11-01']],
            'too long' => [Tools\GetSteps::class, ['start_date' => '2026-01-01', 'end_date' => '2026-02-01']],
            'sleep without dates' => [Tools\GetSleep::class, []],
            'limit too high' => [Tools\SearchActivities::class, ['limit' => 101]],
            'bad activity type' => [Tools\SearchActivities::class, ['activity_type' => 'a&b=c']],
        ];
    }

    public function test_a_range_of_exactly_the_limit_is_accepted(): void
    {
        Http::fake(['*' => Http::response([])]);

        GarminServer::tool(Tools\GetSteps::class, ['start_date' => '2026-01-01', 'end_date' => '2026-01-31'])->assertOk();
    }

    public function test_garmin_errors_reach_claude_as_tool_errors(): void
    {
        Http::fake(['*' => Http::response('', 429)]);

        GarminServer::tool(Tools\GetSteps::class, self::RANGE)->assertHasErrors(['rate limit']);
    }

    public function test_an_empty_answer_says_so(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        GarminServer::tool(Tools\GetHeartRateZones::class)->assertOk()->assertSee('no data');
    }
}
