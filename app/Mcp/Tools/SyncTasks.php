<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Tag;
use App\Models\Task;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SyncTasks extends Tool
{
    protected string $title = 'Sync Tasks';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Sync a list of tasks from OpenClaw to the Laravel database. Updates existing tasks if _timepro.id is provided, otherwise creates new ones. This tool can also be used to update existing task by passing timepro_task_id';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task' => $schema->string()->description('The title of the task')->required(),
            'project' => $schema->string()->description('The project/tag name to search for')->required(),
            'priority' => $schema->string()->description('Priority (P1, P2, P3, P4)')->required(),
            'estimated_minutes' => $schema->integer()->description('Estimated time in minutes')->required(),
            'assignee_id' => $schema->integer()->description('The ID of the user to assign the task to (Defaults to authenticated user)'),
            'timepro_task_id' => $schema->integer()->description('The existing Task ID in Laravel (if updating)'),
            'description' => $schema->string()->description('Optional. Description of the task.'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $taskId = $request->get('timepro_task_id') ?? null;

        if (empty($request->get('task') ?? '')) {
            return Response::error('Task title is required.');
        }

        $validPriorities = ['P1', 'P2', 'P3', 'P4'];
        $priority = $request->get('priority') ?? 'P2';

        if (! in_array($priority, $validPriorities, true)) {
            return Response::error("Invalid priority '{$priority}'. Must be one of: P1, P2, P3, P4.");
        }

        $validEstimates = array_keys(config('options.estimate'));
        $estimate = $request->get('estimated_minutes') ?? 60;

        if (! in_array((int) $estimate, $validEstimates, true)) {
            return Response::error("Invalid estimated_minutes '{$estimate}'. Must be one of: ".implode(', ', $validEstimates).'.');
        }

        $assigneeId = $request->get('assignee_id') ?? auth()->id() ?? 1;

        if (! \App\Models\User::where('id', $assigneeId)->exists()) {
            return Response::error("Assignee with ID {$assigneeId} not found.");
        }

        $isUrgent = in_array($priority, ['P1', 'P3']);
        $isImportant = in_array($priority, ['P1', 'P2']);

        // Find Tag (Project)
        $projectName = $request->get('project') ?? null;
        $tag = null;
        if ($projectName) {
            $tag = Tag::findFromStringOfAnyType($projectName)->first();

            if (! $tag) {
                // Throw error as reponse, Tag is invalid.
                return Response::error("Tag '{$projectName}' not found. Please use a valid tag.");
            }
        }

        $attributes = [
            'title' => $request->get('task'),
            'description' => $request->get('description') ?? null,
            'estimate' => $estimate,
            'is_urgent' => $isUrgent,
            'is_important' => $isImportant,
            'assignee_id' => $assigneeId,
            'auto_schedule' => true,
        ];

        if ($taskId) {
            $task = Task::find($taskId);
            if ($task) {
                if (! Gate::allows('update', $task)) {
                    return Response::json([
                        'status' => 'error',
                        'message' => "You are not authorized to update task {$taskId}.",
                    ]);
                }
                $task->update($attributes);
            } else {
                $task = Task::create($attributes);
            }
        } else {
            $task = Task::create($attributes);
        }

        // Attach Tag if found
        if ($tag) {
            $task->syncTags([$tag->name]);
        }

        dispatch(new ScheduleTasksForUser($attributes['assignee_id']));

        return Response::json([
            'status' => 'success',
            'message' => 'Task synced successfully.',
            'timepro_task_id' => $task->id,
            'task' => $task->refresh()->toArray(),
        ]);
    }
}
