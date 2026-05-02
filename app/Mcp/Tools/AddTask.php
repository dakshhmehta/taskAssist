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
        return $schema->string('task', 'The title of the task')
            ->string('project', 'The project/tag name to search for')
            ->string('priority', 'Priority (P1, P2, P3, P4)')
            ->integer('estimated_minutes', 'Estimated time in minutes')
            ->object('_timepro', 'Task identification metadata (must be empty)', function ($meta) {
                $meta->integer('id', 'Task ID (should not be present for new tasks)');
            });
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        if (isset($arguments['_timepro']['id'])) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'AddTask failed: _timepro.id detected. Use SyncTasks for existing tasks.',
            ]);
        }

        // Map Priority
        $priority = $arguments['priority'] ?? 'P2';
        $isUrgent = in_array($priority, ['P1', 'P3']);
        $isImportant = in_array($priority, ['P1', 'P2']);

        // Find Tag (Project)
        $projectName = $arguments['project'] ?? '';
        $tag = Tag::where('name', 'LIKE', '%' . $projectName . '%')->first();

        $task = Task::create([
            'title' => $arguments['task'],
            'estimate' => $arguments['estimated_minutes'] ?? 60,
            'is_urgent' => $isUrgent,
            'is_important' => $isImportant,
            'assignee_id' => 1,
            'auto_schedule' => true,
        ]);

        // Attach Tag if found
        if ($tag) {
            $task->syncTags([$tag->name]);
        }

        dispatch(new ScheduleTasksForUser(1));

        return ToolResult::json([
            'status' => 'success',
            'message' => 'Task added successfully.',
            'task_id' => $task->id,
        ]);
    }
}
