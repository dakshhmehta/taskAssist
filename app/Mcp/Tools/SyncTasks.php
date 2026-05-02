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
        return $schema->array('tasks', 'A list of tasks to sync')
            ->items(function ($item) {
                $item->string('task', 'The title of the task');
                $item->string('project', 'The project/tag name to search for');
                $item->string('priority', 'Priority (P1, P2, P3, P4)');
                $item->integer('estimated_minutes', 'Estimated time in minutes');
                $item->integer('assignee_id', 'The ID of the user to assign the task to (Defaults to 1)');
                $item->object('_timepro', 'Task identification metadata', function ($meta) {
                    $meta->integer('id', 'The existing Task ID in Laravel');
                });
            });
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $tasks = $arguments['tasks'] ?? [];
        $syncedCount = 0;

        foreach ($tasks as $taskData) {
            $taskId = $taskData['_timepro']['id'] ?? null;
            
            // Map Priority
            $priority = $taskData['priority'] ?? 'P2';
            $isUrgent = in_array($priority, ['P1', 'P3']);
            $isImportant = in_array($priority, ['P1', 'P2']);

            // Find Tag (Project)
            $projectName = $taskData['project'] ?? '';
            $tag = Tag::where('name', 'LIKE', '%' . $projectName . '%')->first();

            $attributes = [
                'title' => $taskData['task'],
                'estimate' => $taskData['estimated_minutes'] ?? 60,
                'is_urgent' => $isUrgent,
                'is_important' => $isImportant,
                'assignee_id' => $taskData['assignee_id'] ?? 1,
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

            $syncedCount++;
        }

        if ($syncedCount > 0) {
            dispatch(new ScheduleTasksForUser(1));
        }

        return ToolResult::json([
            'status' => 'success',
            'message' => "Synced $syncedCount tasks.",
        ]);
    }
}
