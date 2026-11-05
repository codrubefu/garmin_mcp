<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_hrv')]
#[Description('Get heart rate variability (HRV) readings for one day (pass date), or daily HRV summaries for a date range (pass start_date and end_date).')]
class GetHrv extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->dayOrRangeSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        if ($request->get('date') !== null) {
            return $garmin->get("/hrv-service/hrv/{$this->date($request)}");
        }

        [$start, $end] = $this->range($request);

        return $garmin->get("/hrv-service/hrv/daily/{$start}/{$end}");
    }
}
