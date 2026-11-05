<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_food_log')]
#[Description('Get the nutrition food log for one day.')]
class GetFoodLog extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->dateSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        $date = $this->date($request);

        return $garmin->get("/nutrition-service/food/logs/{$date}");
    }
}
