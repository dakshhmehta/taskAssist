<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SetTimesheet extends Tool
{
    protected string $title = 'Set Timesheet';

    public function description(): string
    {
        return 'Replace all timesheet entries for a specific task and user. Deletes existing entries and creates corrected ones in a single transaction. Only touches the specified user — other users on the same task are never affected.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('The task ID to correct timesheets for.')->required(),
            'user_id' => $schema->integer()->description('The user ID whose entries should be replaced.')->required(),
            'entries' => $schema->string()->description('JSON array of timesheet entries. Each: {"duration_minutes":50,"date":"2026-06-04"} . Date defaults to task completed_at date if omitted.')->required(),
            'reason' => $schema->string()->description('Human-readable explanation for the correction (for audit trail).')->required(),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        $taskId = $request->get('task_id');
        $userId = $request->get('user_id');

        $task = Task::find($taskId);

        if (! $task) {
            return Response::json([
                'status' => 'error',
                'message' => "Task {$taskId} not found.",
            ]);
        }

        if (! Gate::allows('update', $task)) {
            return Response::json([
                'status' => 'error',
                'message' => "You are not authorized to modify timesheets for task {$taskId}.",
            ]);
        }

        if (! User::where('id', $userId)->exists()) {
            return Response::json([
                'status' => 'error',
                'message' => "User {$userId} not found.",
            ]);
        }

        $entries = json_decode($request->get('entries'), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($entries) || empty($entries)) {
            return Response::json([
                'status' => 'error',
                'message' => 'entries must be a valid JSON array with at least one entry.',
            ]);
        }

        $defaultDate = $task->completed_at
            ? $this->normalizeEntryDate($task->completed_at)->format('Y-m-d')
            : now()->format('Y-m-d');

        try {
            [$deleted, $created] = DB::transaction(function () use ($taskId, $userId, $entries, $defaultDate) {
                $deleted = Timesheet::where('task_id', $taskId)
                    ->where('user_id', $userId)
                    ->delete();

                foreach ($entries as $entry) {
                    $duration = (int) ($entry['duration_minutes'] ?? 0);
                    $date = $this->normalizeEntryDate($entry['date'] ?? $defaultDate);

                    Timesheet::create([
                        'task_id' => $taskId,
                        'user_id' => $userId,
                        'start_at' => $date->copy()->startOfDay(),
                        'end_at' => $date->copy()->startOfDay()->addMinutes($duration),
                    ]);
                }

                return [$deleted, count($entries)];
            });

            return Response::json([
                'status' => 'success',
                'message' => "Timesheet corrected for user {$userId} on task {$taskId}. Deleted {$deleted} entries, created {$created}.",
                'deleted' => $deleted,
                'created' => $created,
                'task_id' => (int) $taskId,
                'user_id' => (int) $userId,
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Failed to set timesheet: '.$e->getMessage(),
            ]);
        }
    }

    private function normalizeEntryDate(mixed $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy();
        }

        return Carbon::parse($date);
    }
}
