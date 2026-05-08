<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Task;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Complete Task')]
class CompleteTask extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Mark a task as completed in the Laravel database.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('timepro_task_id', 'The ID of the task to complete')->required();
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $taskId = $arguments['timepro_task_id'] ?? null;

        $task = Task::find($taskId);

        if (!$task || $task->completed_at) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "No open task found with ID: $taskId",
            ]);
        }

        $task->complete(); // Uses the complete() method in Task model

        dispatch(new ScheduleTasksForUser(1));

        return ToolResult::json([
            'status' => 'success',
            'task_id' => $task->id,
            'task' => $task->toArray(),
            'message' => "Task completed: {$task->title}",
        ]);
    }
}
