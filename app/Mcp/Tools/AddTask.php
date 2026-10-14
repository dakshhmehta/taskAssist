<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Tag;
use App\Models\Task;
use App\Notifications\NewTaskAssignedNotification;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AddTask extends Tool
{
    protected string $title = 'Add Task';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Add a new task to the Laravel database. Fails if the task already has a _timepro.id.';
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
            'timepro_task_id' => $schema->integer()->description('Task ID (should not be present for new tasks)'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        if ($request->get('timepro_task_id') !== null) {
            return Response::json([
                'status' => 'error',
                'message' => 'AddTask failed: timepro_task_id detected. Use SyncTasks for existing tasks.',
            ]);
        }

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
        $projectName = $request->get('project') ?? '';
        if (empty($projectName)) {
            return Response::error('Project/tag name was not provided. Please retry with the correct tag.');
        }

        $tag = Tag::findFromStringOfAnyType($projectName)->first();

        if (! $tag) {
            return Response::error("Tag '{$projectName}' not found. Please retry with the correct tag.");
        }

        $task = Task::create([
            'title' => $request->get('task'),
            'estimate' => $estimate,
            'is_urgent' => $isUrgent,
            'is_important' => $isImportant,
            'assignee_id' => $assigneeId,
            'auto_schedule' => true,
        ]);

        // Attach Tag if found
        if ($tag) {
            $task->syncTags([$tag->name]);
        }

        dispatch(new ScheduleTasksForUser($assigneeId));

        if ($assigneeId != auth()->id()) {
            $task->assignee->notify(new NewTaskAssignedNotification($task));
        }

        return Response::json([
            'status' => 'success',
            'message' => 'Task added successfully.',
            'task_id' => $task->id,
            'task' => $task->toArray(),
        ]);
    }
}
