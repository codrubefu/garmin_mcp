<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use App\Garmin\GarminException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

abstract class GarminTool extends Tool
{
    private const DATE_RULE = 'date_format:Y-m-d';

    abstract protected function fetch(Request $request, GarminClient $garmin): mixed;

    public function handle(Request $request, GarminClient $garmin): Response
    {
        try {
            $data = $this->fetch($request, $garmin);
        } catch (GarminException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json($data ?? ['message' => 'Garmin returned no data for this request.']);
    }

    /**
     * Attributes are not inherited, so this marks every tool as read-only in one place.
     *
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dateSchema(JsonSchema $schema): array
    {
        return ['date' => $schema->string()->description('Calendar date as YYYY-MM-DD.')->required()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rangeSchema(JsonSchema $schema): array
    {
        $limit = config('garmin.max_range_days');

        return [
            'start_date' => $schema->string()->description('First day as YYYY-MM-DD.')->required(),
            'end_date' => $schema->string()->description("Last day as YYYY-MM-DD, included in the results. At most {$limit} days in total.")->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dayOrRangeSchema(JsonSchema $schema): array
    {
        $limit = config('garmin.max_range_days');

        return [
            'date' => $schema->string()->description('A single day as YYYY-MM-DD. Leave out when using a range.'),
            'start_date' => $schema->string()->description('First day of a range as YYYY-MM-DD.'),
            'end_date' => $schema->string()->description("Last day of a range as YYYY-MM-DD, at most {$limit} days in total."),
        ];
    }

    protected function date(Request $request): string
    {
        return $request->validate(['date' => ['required', self::DATE_RULE]])['date'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function range(Request $request): array
    {
        $valid = $request->validate([
            'start_date' => ['required', self::DATE_RULE],
            'end_date' => ['required', self::DATE_RULE, 'after_or_equal:start_date'],
        ]);

        $limit = config('garmin.max_range_days');
        $days = Carbon::parse($valid['start_date'])->diffInDays($valid['end_date']) + 1;

        if ($days > $limit) {
            throw ValidationException::withMessages([
                'end_date' => "The range covers {$days} days, and the limit is {$limit}. Split it into smaller ranges.",
            ]);
        }

        return [$valid['start_date'], $valid['end_date']];
    }
}
