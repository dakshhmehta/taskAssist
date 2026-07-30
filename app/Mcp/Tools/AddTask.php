<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Tag;
use App\Models\Task;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Add Task')]
class AddTask extends Tool
{
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
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('task', 'The title of the task')->required()
            ->string('project', 'The project/tag name to search for')->required()
            ->string('priority', 'Priority (P1, P2, P3, P4)')->required()
            ->integer('estimated_minutes', 'Estimated time in minutes')->required()
            ->integer('assignee_id', 'The ID of the user to assign the task to (Defaults to authenticated user)')
            ->integer('timepro_task_id', 'Task ID (should not be present for new tasks)');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        if (isset($arguments['timepro_task_id'])) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'AddTask failed: timepro_task_id detected. Use SyncTasks for existing tasks.',
            ]);
        }

        if (empty($arguments['task'] ?? '')) {
            return ToolResult::error('Task title is required.');
        }

        $validPriorities = ['P1', 'P2', 'P3', 'P4'];
        $priority = $arguments['priority'] ?? 'P2';

        if (!in_array($priority, $validPriorities, true)) {
            return ToolResult::error("Invalid priority '{$priority}'. Must be one of: P1, P2, P3, P4.");
        }

        $validEstimates = array_keys(config('options.estimate'));
        $estimate = $arguments['estimated_minutes'] ?? 60;

        if (!in_array((int) $estimate, $validEstimates, true)) {
            return ToolResult::error("Invalid estimated_minutes '{$estimate}'. Must be one of: " . implode(', ', $validEstimates) . ".");
        }

        $assigneeId = $arguments['assignee_id'] ?? auth()->id() ?? 1;

        if (!\App\Models\User::where('id', $assigneeId)->exists()) {
            return ToolResult::error("Assignee with ID {$assigneeId} not found.");
        }

        $isUrgent = in_array($priority, ['P1', 'P3']);
        $isImportant = in_array($priority, ['P1', 'P2']);

        // Find Tag (Project)
        $projectName = $arguments['project'] ?? '';
        if (empty($projectName)) {
            return ToolResult::error("Project/tag name was not provided. Please retry with the correct tag.");
        }

        $tag = Tag::findFromStringOfAnyType($projectName)->first();

        if (!$tag) {
            return ToolResult::error("Tag '{$projectName}' not found. Please retry with the correct tag.");
        }

        $task = Task::create([
            'title' => $arguments['task'],
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

        return ToolResult::json([
            'status' => 'success',
            'message' => 'Task added successfully.',
            'task_id' => $task->id,
            'task' => $task->toArray(),
        ]);
    }
}
