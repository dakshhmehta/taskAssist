<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Set Timer')]
class SetTimer extends Tool
{
    public function description(): string
    {
        return 'Start or stop the timer on a task for the authenticated user.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('task_id', 'The ID of the task to start or stop the timer on.')->required()
            ->string('action', 'Either "start" or "stop".')->required();
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $taskId = $arguments['task_id'];
        $action = strtolower($arguments['action']);

        if (! in_array($action, ['start', 'stop'])) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Action must be "start" or "stop".',
            ]);
        }

        $task = Task::find($taskId);

        if (! $task) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Task {$taskId} not found.",
            ]);
        }

        if ($task->is_completed) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Task {$taskId} is already completed.",
            ]);
        }

        if ($action === 'start') {
            $result = $task->startTimer();

            if ($result) {
                return ToolResult::json([
                    'status' => 'success',
                    'message' => "Timer started on task {$taskId}: {$task->title}",
                    'task_id' => (int) $taskId,
                ]);
            }

            return ToolResult::json([
                'status' => 'error',
                'message' => "Timer is already running on task {$taskId}.",
            ]);
        }

        if ($action === 'stop') {
            $result = $task->endTimer();

            if ($result) {
                return ToolResult::json([
                    'status' => 'success',
                    'message' => "Timer stopped on task {$taskId}: {$task->title}",
                    'task_id' => (int) $taskId,
                ]);
            }

            return ToolResult::json([
                'status' => 'error',
                'message' => "No running timer found for task {$taskId}.",
            ]);
        }
    }
}
