<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_wellness_activities')]
#[Description('Get wellness activity summaries for a date range.')]
class GetWellnessActivities extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->rangeSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        [$start, $end] = $this->range($request);

        return $garmin->get("/wellnessactivity-service/activity/summary/range/{$start}/{$end}");
    }
}
