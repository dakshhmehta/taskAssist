<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Tag;
use App\Models\Task;
use Generator;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Sync Tasks')]
class SyncTasks extends Tool
{
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
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('task', 'The title of the task')->required()
            ->string('project', 'The project/tag name to search for')->required()
            ->string('priority', 'Priority (P1, P2, P3, P4)')->required()
            ->integer('estimated_minutes', 'Estimated time in minutes')->required()
            ->integer('assignee_id', 'The ID of the user to assign the task to (Defaults to authenticated user)')
            ->integer('timepro_task_id', 'The existing Task ID in Laravel (if updating)')
            ->string('description', 'Optional. Description of the task.');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $taskId = $arguments['timepro_task_id'] ?? null;

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
        $projectName = $arguments['project'] ?? null;
        $tag = null;
        if($projectName){
            $tag = Tag::findFromStringOfAnyType($projectName)->first();
    
            if(! $tag){
                // Throw error as reponse, Tag is invalid. 
                return ToolResult::error("Tag '{$projectName}' not found. Please use a valid tag.");
            }
        }

        $attributes = [
            'title' => $arguments['task'],
            'description' => $arguments['description'] ?? null,
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
                    return ToolResult::json([
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

        return ToolResult::json([
            'status' => 'success',
            'message' => 'Task synced successfully.',
            'timepro_task_id' => $task->id,
            'task' => $task->refresh()->toArray(),
        ]);
    }
}
