<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('search_activities')]
#[Description('Search recorded activities, newest first. Returns summaries that include the activity IDs used by get_activity.')]
class SearchActivities extends GarminTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'start_date' => $schema->string()->description('Only activities on or after this day, as YYYY-MM-DD.'),
            'end_date' => $schema->string()->description('Only activities on or before this day, as YYYY-MM-DD.'),
            'activity_type' => $schema->string()->description('Garmin activity type key, for example running or strength_training.'),
            'start' => $schema->integer()->min(0)->description('Number of results to skip, for paging. Defaults to 0.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Maximum results to return. Defaults to 20.'),
        ];
    }

    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        $valid = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'activity_type' => ['nullable', 'regex:/^[a-z0-9_]+$/D'],
            'start' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $garmin->get('/activitylist-service/activities/search/activities', array_filter([
            'start' => $valid['start'] ?? 0,
            'limit' => $valid['limit'] ?? 20,
            'startDate' => $valid['start_date'] ?? null,
            'endDate' => $valid['end_date'] ?? null,
            'activityType' => $valid['activity_type'] ?? null,
        ], fn (mixed $value): bool => $value !== null));
    }
}
