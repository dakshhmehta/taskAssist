<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AddTaskComment extends Tool
{
    protected string $title = 'Add Task Comment';

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
    public function schema(JsonSchema $schema): array
    {
        return [
            'timepro_task_id' => $schema->integer()->description('The ID of the task to comment on')->required(),
            'comment' => $schema->string()->description('The comment text to add')->required(),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $taskId = $request->get('timepro_task_id') ?? null;
        $comment = trim((string) ($request->get('comment') ?? ''));

        if (! $taskId) {
            return Response::json([
                'status' => 'error',
                'message' => 'timepro_task_id is required.',
            ]);
        }

        if ($comment === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'comment is required.',
            ]);
        }

        $task = Task::find($taskId);

        if (! $task) {
            return Response::json([
                'status' => 'error',
                'message' => "No task found with ID: {$taskId}",
            ]);
        }

        $created = $task->filamentComments()->create([
            'subject_type' => $task->getMorphClass(),
            'comment' => $comment,
            'user_id' => auth()->id() ?? 1,
        ]);

        return Response::json([
            'status' => 'success',
            'task_id' => $task->id,
            'comment' => array_merge($created->toArray(), [
                'user' => $created->user?->only(['id', 'name', 'email']),
            ]),
            'message' => "Comment added to task: {$task->title}",
        ]);
    }
}
