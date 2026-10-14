<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Task;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CompleteTask extends Tool
{
    protected string $title = 'Complete Task';

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
    public function schema(JsonSchema $schema): array
    {
        return [
            'timepro_task_id' => $schema->integer()->description('The ID of the task to complete')->required(),
            'closing_remarks' => $schema->string()->description('Optional completion note to add as a task comment before closing.'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $taskId = $request->get('timepro_task_id') ?? null;
        $closingRemarks = $request->get('closing_remarks') !== null ? trim((string) $request->get('closing_remarks')) : null;

        $task = Task::find($taskId);

        if (! $task || $task->completed_at) {
            return Response::json([
                'status' => 'error',
                'message' => "No open task found with ID: $taskId",
            ]);
        }

        if (! $task->isCompletable()) {
            return Response::json([
                'status' => 'error',
                'message' => "Task {$taskId} cannot be completed. The timer must be stopped and you must be the assignee or an admin.",
            ]);
        }

        $task->endTimer();

        if (filled($closingRemarks)) {
            $task->filamentComments()->create([
                'subject_type' => $task->getMorphClass(),
                'comment' => $closingRemarks,
                'user_id' => auth()->id(),
            ]);
        }

        $task->complete();

        dispatch(new ScheduleTasksForUser(1));

        return Response::json([
            'status' => 'success',
            'task_id' => $task->id,
            'task' => $task->toArray(),
            'message' => "Task completed: {$task->title}",
        ]);
    }
}
