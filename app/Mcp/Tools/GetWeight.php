<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_weight')]
#[Description('Get all weight and body composition measurements for a date range.')]
class GetWeight extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->rangeSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        [$start, $end] = $this->range($request);

        return $garmin->get("/weight-service/weight/range/{$start}/{$end}", ['includeAll' => 'true']);
    }
}
