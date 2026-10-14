<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetTask extends Tool
{
    protected string $title = 'Get Task';

    public function description(): string
    {
        return 'Get full task details by task ID. Returns the task with its assignee user object, tags, timesheet entries, comments, and activity log.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'timepro_task_id' => $schema->integer()->description('The ID of the task to retrieve.'),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        $taskId = $request->get('timepro_task_id') ?? null;

        if (! $taskId) {
            return Response::json([
                'status' => 'error',
                'message' => 'timepro_task_id is required.',
            ]);
        }

        $task = Task::query()
            ->with(['assignee', 'tags', 'timesheet.user', 'filamentComments', 'activities.causer'])
            ->find($taskId);

        if (! $task) {
            return Response::json([
                'status' => 'error',
                'message' => "No task found with ID: {$taskId}",
            ]);
        }

        return Response::json([
            'status' => 'success',
            'task' => array_merge($task->append([
                'is_completed', 'in_progress', 'minutes_taken', 'hms', 'performance', 'cost', 'display_title',
            ])->toArray(), [
                'assignee' => $task->assignee?->only(['id', 'name', 'email']),
                'tags' => $task->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => is_array($tag->name) ? ($tag->name['en'] ?? reset($tag->name)) : $tag->name,
                    'type' => $tag->type,
                ])->values()->all(),
                'timesheet' => $task->timesheet->map(fn ($entry) => array_merge($entry->toArray(), [
                    'user' => $entry->user?->only(['id', 'name', 'email']),
                ]))->values()->all(),
                'comments' => $task->filamentComments->map(fn ($comment) => array_merge($comment->toArray(), [
                    'user' => $comment->user?->only(['id', 'name', 'email']),
                ]))->values()->all(),
                'activity_log' => $task->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'causer' => $activity->causer?->only(['id', 'name', 'email']),
                    'properties' => $activity->properties,
                    'created_at' => $activity->created_at,
                ])->values()->all(),
            ]),
        ]);
    }
}
