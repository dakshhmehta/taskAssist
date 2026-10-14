<?php

namespace App\Mcp\Tools;

use App\Models\Timesheet;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetTimesheet extends Tool
{
    protected string $title = 'Get Timesheet';

    public function description(): string
    {
        return 'View timesheet entries for a task or user. Preview step before correcting timesheets.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('Filter by task ID.'),
            'user_id' => $schema->integer()->description('Filter by user ID.'),
            'from_date' => $schema->string()->description('Filter entries from this date (Y-m-d).'),
            'to_date' => $schema->string()->description('Filter entries up to this date (Y-m-d).'),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        $taskId = $request->get('task_id') ?? null;
        $userId = $request->get('user_id') ?? null;
        $fromDate = $request->get('from_date') ?? null;
        $toDate = $request->get('to_date') ?? null;

        if (! $taskId && ! $userId) {
            return Response::json([
                'status' => 'error',
                'message' => 'At least task_id or user_id must be provided.',
            ]);
        }

        $query = Timesheet::query()
            ->with('user')
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($fromDate, fn ($q) => $q->whereDate('start_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('start_at', '<=', $toDate))
            ->orderBy('start_at', 'DESC');

        $entries = $query->get()->map(fn ($entry) => [
            'id' => $entry->id,
            'task_id' => $entry->task_id,
            'user_id' => $entry->user_id,
            'user_name' => $entry->user?->name,
            'start_at' => $entry->start_at?->format('Y-m-d H:i:s'),
            'end_at' => $entry->end_at?->format('Y-m-d H:i:s'),
            'duration_minutes' => (int) optional($entry->start_at)->diffInMinutes($entry->end_at),
            'duration_hms' => Timesheet::toHMS((int) optional($entry->start_at)->diffInMinutes($entry->end_at)),
        ]);

        return Response::json([
            'status' => 'success',
            'count' => $entries->count(),
            'entries' => $entries->toArray(),
        ]);
    }
}
