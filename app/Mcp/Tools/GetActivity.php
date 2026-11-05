<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use App\Garmin\GarminException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_activity')]
#[Description('Get the details of one activity and any workouts linked to it. Find the ID with search_activities.')]
class GetActivity extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['activity_id' => $schema->integer()->min(1)->description('Garmin activity ID.')->required()];
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        $id = $request->validate(['activity_id' => ['required', 'integer', 'min:1']])['activity_id'];

        return [
            'activity' => $garmin->get("/activity-service/activity/{$id}"),
            'workouts' => $this->workouts($garmin, $id),
        ];
    }

    private function workouts(GarminClient $garmin, int $id): mixed
    {
        try {
            return $garmin->get("/activity-service/activity/{$id}/workouts");
        } catch (GarminException $e) {
            // Most activities have no linked workout, which must not hide the activity itself.
            return $e->status === 404 ? null : throw $e;
        }
    }
}
