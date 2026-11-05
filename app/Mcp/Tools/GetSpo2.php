<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_spo2')]
#[Description('Get blood oxygen (SpO2) and acclimation data for one day.')]
class GetSpo2 extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->dateSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        $date = $this->date($request);

        return $garmin->get("/wellness-service/wellness/daily/spo2acclimation/{$date}");
    }
}
