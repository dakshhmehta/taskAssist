<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\Timesheet;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SetTimer extends Tool
{
    protected string $title = 'Set Timer';

    public function description(): string
    {
        return 'Start or stop the timer on a task for the authenticated user. Multiple timers can run in parallel. To start a timer, task_id is required. To stop a timer, task_id is optional: when omitted, all running timers for the authenticated user are stopped.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('The ID of the task to start or stop the timer on. Required when action is "start"; optional when action is "stop" (omitting it stops all running timers for the user).'),
            'action' => $schema->string()->description('Either "start" or "stop".')->required(),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        $action = strtolower((string) ($request->get('action') ?? ''));

        if (! in_array($action, ['start', 'stop'])) {
            return Response::json([
                'status' => 'error',
                'message' => 'Action must be "start" or "stop".',
            ]);
        }

        $user = auth()->user();

        if (! $user) {
            return Response::json([
                'status' => 'error',
                'message' => 'No authenticated user found.',
            ]);
        }

        $taskId = $request->get('task_id') ?? null;

        if ($action === 'start') {
            return $this->startTimer($user->id, $taskId);
        }

        return $this->stopTimers($user->id, $taskId);
    }

    private function startTimer(int $userId, mixed $taskId): Response
    {
        if (! $taskId) {
            return Response::json([
                'status' => 'error',
                'message' => 'task_id is required to start a timer.',
            ]);
        }

        $task = Task::find($taskId);

        if (! $task) {
            return Response::json([
                'status' => 'error',
                'message' => "Task {$taskId} not found.",
            ]);
        }

        if ($task->is_completed) {
            return Response::json([
                'status' => 'error',
                'message' => "Task {$taskId} is already completed.",
            ]);
        }

        if ($task->isTimeStarted($userId)) {
            return Response::json([
                'status' => 'error',
                'message' => "Timer is already running on task {$taskId}.",
            ]);
        }

        // Create the timesheet directly instead of calling $task->startTimer(),
        // because startTimer() refuses to run when ANY other timer is running
        // for the user, which blocks parallel timers across tasks.
        $timesheet = new Timesheet([
            'user_id' => $userId,
            'task_id' => $task->id,
            'start_at' => now(),
        ]);

        if (! $timesheet->save()) {
            return Response::json([
                'status' => 'error',
                'message' => "Failed to start timer on task {$taskId}.",
            ]);
        }

        return Response::json([
            'status' => 'success',
            'message' => "Timer started on task {$taskId}: {$task->title}",
            'task_id' => (int) $taskId,
        ]);
    }

    private function stopTimers(int $userId, mixed $taskId): Response
    {
        if ($taskId) {
            return $this->stopSingleTimer($userId, $taskId);
        }

        return $this->stopAllTimers($userId);
    }

    private function stopSingleTimer(int $userId, mixed $taskId): Response
    {
        $task = Task::find($taskId);

        if (! $task) {
            return Response::json([
                'status' => 'error',
                'message' => "Task {$taskId} not found.",
            ]);
        }

        if ($task->is_completed) {
            return Response::json([
                'status' => 'error',
                'message' => "Task {$taskId} is already completed.",
            ]);
        }

        $result = $task->endTimer();

        if ($result) {
            return Response::json([
                'status' => 'success',
                'message' => "Timer stopped on task {$taskId}: {$task->title}",
                'task_id' => (int) $taskId,
            ]);
        }

        return Response::json([
            'status' => 'error',
            'message' => "No running timer found for task {$taskId}.",
        ]);
    }

    private function stopAllTimers(int $userId): Response
    {
        $runningTimers = Timesheet::where('user_id', $userId)
            ->whereNull('end_at')
            ->get();

        $stopped = 0;

        foreach ($runningTimers as $timesheet) {
            $timesheet->end_at = now();

            if ($timesheet->save()) {
                $stopped++;
            }
        }

        if ($stopped === 0) {
            return Response::json([
                'status' => 'success',
                'message' => 'No running timers found for the authenticated user.',
                'stopped' => 0,
            ]);
        }

        $message = $stopped === 1
            ? 'Stopped 1 running timer.'
            : "Stopped {$stopped} running timers.";

        return Response::json([
            'status' => 'success',
            'message' => $message,
            'stopped' => $stopped,
        ]);
    }
}
