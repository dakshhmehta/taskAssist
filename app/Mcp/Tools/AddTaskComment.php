<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Add Task Comment')]
class AddTaskComment extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Add a comment/note to an existing task. Use this for progress notes (e.g. an OTP update) instead of appending to the task description.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema
            ->integer('timepro_task_id', 'The ID of the task to comment on')->required()
            ->string('comment', 'The comment text to add')->required();
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $taskId = $arguments['timepro_task_id'] ?? null;
        $comment = trim((string) ($arguments['comment'] ?? ''));

        if (! $taskId) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'timepro_task_id is required.',
            ]);
        }

        if ($comment === '') {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'comment is required.',
            ]);
        }

        $task = Task::find($taskId);

        if (! $task) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "No task found with ID: {$taskId}",
            ]);
        }

        $created = $task->filamentComments()->create([
            'subject_type' => $task->getMorphClass(),
            'comment' => $comment,
            'user_id' => auth()->id() ?? 1,
        ]);

        return ToolResult::json([
            'status' => 'success',
            'task_id' => $task->id,
            'comment' => array_merge($created->toArray(), [
                'user' => $created->user?->only(['id', 'name', 'email']),
            ]),
            'message' => "Comment added to task: {$task->title}",
        ]);
    }
}
