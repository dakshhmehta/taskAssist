<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Get Task')]
class GetTask extends Tool
{
    public function description(): string
    {
        return 'Get full task details by task ID. Returns the task with its assignee user object, tags, timesheet entries, comments, and activity log.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('timepro_task_id', 'The ID of the task to retrieve.');
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $taskId = $arguments['timepro_task_id'] ?? null;

        if (! $taskId) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'timepro_task_id is required.',
            ]);
        }

        $task = Task::query()
            ->with(['assignee', 'tags', 'timesheet.user', 'filamentComments', 'activities.causer'])
            ->find($taskId);

        if (! $task) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "No task found with ID: {$taskId}",
            ]);
        }

        return ToolResult::json([
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
