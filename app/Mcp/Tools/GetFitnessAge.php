<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_fitness_age')]
#[Description('Get fitness age data for one day.')]
class GetFitnessAge extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->dateSchema($schema);
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        $date = $this->date($request);

        return $garmin->get("/fitnessage-service/fitnessage/v2/{$date}");
    }
}
