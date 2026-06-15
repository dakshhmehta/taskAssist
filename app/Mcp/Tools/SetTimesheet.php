<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Set Timesheet')]
class SetTimesheet extends Tool
{
    public function description(): string
    {
        return 'Replace all timesheet entries for a specific task and user. Deletes existing entries and creates corrected ones in a single transaction. Only touches the specified user — other users on the same task are never affected.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('task_id', 'The task ID to correct timesheets for.')->required()
            ->integer('user_id', 'The user ID whose entries should be replaced.')->required()
            ->string('entries', 'JSON array of timesheet entries. Each: {"duration_minutes":50,"date":"2026-06-04"} . Date defaults to task completed_at date if omitted.')->required()
            ->string('reason', 'Human-readable explanation for the correction (for audit trail).')->required();
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $taskId = $arguments['task_id'];
        $userId = $arguments['user_id'];

        $task = Task::find($taskId);

        if (! $task) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Task {$taskId} not found.",
            ]);
        }

        if (! Gate::allows('update', $task)) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "You are not authorized to modify timesheets for task {$taskId}.",
            ]);
        }

        if (! User::where('id', $userId)->exists()) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "User {$userId} not found.",
            ]);
        }

        $entries = json_decode($arguments['entries'], true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($entries) || empty($entries)) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'entries must be a valid JSON array with at least one entry.',
            ]);
        }

        $defaultDate = $task->completed_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        try {
            [$deleted, $created] = DB::transaction(function () use ($taskId, $userId, $entries, $defaultDate) {
                $deleted = Timesheet::where('task_id', $taskId)
                    ->where('user_id', $userId)
                    ->delete();

                foreach ($entries as $entry) {
                    $duration = (int) ($entry['duration_minutes'] ?? 0);
                    $date = $entry['date'] ?? $defaultDate;

                    Timesheet::create([
                        'task_id' => $taskId,
                        'user_id' => $userId,
                        'start_at' => Carbon::parse("{$date} 00:00:00"),
                        'end_at' => Carbon::parse("{$date} 00:00:00")->addMinutes($duration),
                    ]);
                }

                return [$deleted, count($entries)];
            });

            return ToolResult::json([
                'status' => 'success',
                'message' => "Timesheet corrected for user {$userId} on task {$taskId}. Deleted {$deleted} entries, created {$created}.",
                'deleted' => $deleted,
                'created' => $created,
                'task_id' => (int) $taskId,
                'user_id' => (int) $userId,
            ]);
        } catch (\Exception $e) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Failed to set timesheet: ' . $e->getMessage(),
            ]);
        }
    }
}
