<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_sleep')]
#[Description('Get detailed sleep data for one day (pass date), or daily sleep summaries for a date range (pass start_date and end_date).')]
class GetSleep extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->dayOrRangeSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        if ($request->get('date') !== null) {
            return $garmin->get('/sleep-service/sleep/dailySleep', ['date' => $this->date($request)]);
        }

        [$start, $end] = $this->range($request);

        return $garmin->get('/sleep-service/sleep/dailySleepSummariesByDate', ['startDate' => $start, 'endDate' => $end]);
    }
}
