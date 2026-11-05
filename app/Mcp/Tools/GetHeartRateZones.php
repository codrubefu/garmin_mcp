<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_heart_rate_zones')]
#[Description('Get the heart rate zones configured for the user.')]
class GetHeartRateZones extends GarminTool
{
    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        return $garmin->get('/biometric-service/heartRateZones/v2');
    }
}
