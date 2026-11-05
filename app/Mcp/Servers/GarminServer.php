<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Garmin Connect')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Read-only access to the owner's own Garmin Connect data: activities, sleep, recovery,
    daily activity, weight and nutrition. All dates use YYYY-MM-DD. Date ranges include
    both ends. Use get_profile_settings when you need the units behind a value.
    MARKDOWN)]
class GarminServer extends Server
{
    // The default page of 15 would hide tools from clients that don't follow the cursor.
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        Tools\SearchActivities::class,
        Tools\GetActivity::class,
        Tools\GetSleep::class,
        Tools\GetHrv::class,
        Tools\GetBodyBattery::class,
        Tools\GetStress::class,
        Tools\GetHeartRate::class,
        Tools\GetSpo2::class,
        Tools\GetDailySummaries::class,
        Tools\GetSteps::class,
        Tools\GetHydration::class,
        Tools\GetWellnessActivities::class,
        Tools\GetFitnessAge::class,
        Tools\GetHeartRateZones::class,
        Tools\GetWeight::class,
        Tools\GetWeightGoals::class,
        Tools\GetFoodLog::class,
        Tools\GetProfileSettings::class,
    ];
}
