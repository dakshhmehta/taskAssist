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

#[Title('Sync Tasks')]
class SyncTasks extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Sync a list of tasks from OpenClaw to the Laravel database. Updates existing tasks if _timepro.id is provided, otherwise creates new ones.';
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
            ->integer('timepro_task_id', 'The existing Task ID in Laravel (if updating)');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $taskId = $arguments['timepro_task_id'] ?? null;

        // Map Priority
        $priority = $arguments['priority'] ?? 'P2';
        $isUrgent = in_array($priority, ['P1', 'P3']);
        $isImportant = in_array($priority, ['P1', 'P2']);

        // Find Tag (Project)
        $projectName = $arguments['project'] ?? '';
        $tag = Tag::where('name', $projectName )->first();

        if(! $tag){
            // Throw error as reponse, Tag is invalid. 
            return ToolResult::error("Tag '{$projectName}' not found. Please use a valid tag.");
        }

        $attributes = [
            'title' => $arguments['task'],
            'estimate' => $arguments['estimated_minutes'] ?? 60,
            'is_urgent' => $isUrgent,
            'is_important' => $isImportant,
            'assignee_id' => $arguments['assignee_id'] ?? auth()->id() ?? 1,
            'auto_schedule' => true,
        ];

        if ($taskId) {
            $task = Task::find($taskId);
            if ($task) {
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
            'task' => $task->toArray(),
        ]);
    }
}
