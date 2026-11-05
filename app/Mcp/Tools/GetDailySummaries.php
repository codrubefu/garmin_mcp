<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_daily_summaries')]
#[Description('Get overall daily summaries for a date range. Use the same start and end date for one day.')]
class GetDailySummaries extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->rangeSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        [$start, $end] = $this->range($request);

        return $garmin->get('/usersummary-service/v2/dailysummaries', ['startDate' => $start, 'endDate' => $end]);
    }
}
