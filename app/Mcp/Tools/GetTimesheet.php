<?php

namespace App\Mcp\Tools;

use App\Models\Timesheet;
use App\Models\User;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Get Timesheet')]
class GetTimesheet extends Tool
{
    public function description(): string
    {
        return 'View timesheet entries for a task or user. Preview step before correcting timesheets.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('task_id', 'Filter by task ID.')
            ->integer('user_id', 'Filter by user ID.')
            ->string('from_date', 'Filter entries from this date (Y-m-d).')
            ->string('to_date', 'Filter entries up to this date (Y-m-d).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $taskId = $arguments['task_id'] ?? null;
        $userId = $arguments['user_id'] ?? null;
        $fromDate = $arguments['from_date'] ?? null;
        $toDate = $arguments['to_date'] ?? null;

        if (! $taskId && ! $userId) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'At least task_id or user_id must be provided.',
            ]);
        }

        $query = Timesheet::query()
            ->with('user')
            ->when($taskId, fn($q) => $q->where('task_id', $taskId))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($fromDate, fn($q) => $q->whereDate('start_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('start_at', '<=', $toDate))
            ->orderBy('start_at', 'DESC');

        $entries = $query->get()->map(fn($entry) => [
            'id' => $entry->id,
            'task_id' => $entry->task_id,
            'user_id' => $entry->user_id,
            'user_name' => $entry->user?->name,
            'start_at' => $entry->start_at?->format('Y-m-d H:i:s'),
            'end_at' => $entry->end_at?->format('Y-m-d H:i:s'),
            'duration_minutes' => (int) optional($entry->start_at)->diffInMinutes($entry->end_at),
            'duration_hms' => Timesheet::toHMS((int) optional($entry->start_at)->diffInMinutes($entry->end_at)),
        ]);

        return ToolResult::json([
            'status' => 'success',
            'count' => $entries->count(),
            'entries' => $entries->toArray(),
        ]);
    }
}
