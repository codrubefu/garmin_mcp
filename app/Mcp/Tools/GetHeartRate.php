<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_heart_rate')]
#[Description('Get heart rate data for one day, including resting heart rate.')]
class GetHeartRate extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->dateSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        $date = $this->date($request);

        return $garmin->get('/wellness-service/wellness/dailyHeartRate', ['date' => $date]);
    }
}
