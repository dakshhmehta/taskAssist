<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Tag;
use App\Models\Task;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTask extends Tool
{
    protected string $title = 'Update Task';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Update an existing task in the database with task_id. Accepts task, project, priority, estimate, assignee_id. Only pass what is needed to update. Do not pass same data.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('The ID of the task to update')->required(),
            'task' => $schema->string()->description('The new title of the task'),
            'project' => $schema->string()->description('The new project/tag name to search for'),
            'priority' => $schema->string()->description('New Priority (P1, P2, P3, P4)'),
            'estimated_minutes' => $schema->integer()->description('New estimated time in minutes'),
            'assignee_id' => $schema->integer()->description('The new ID of the user to assign the task to'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $task = Task::find($request->get('task_id'));

        if (! $task) {
            return Response::json([
                'status' => 'error',
                'message' => "Task with ID {$request->get('task_id')} not found.",
            ]);
        }

        $oldAssigneeId = $task->assignee_id;
        $updateData = [];

        if ($request->get('task') !== null) {
            $updateData['title'] = $request->get('task');
        }

        if ($request->get('estimated_minutes') !== null) {
            $updateData['estimate'] = $request->get('estimated_minutes');
        }

        if ($request->get('priority') !== null) {
            $priority = $request->get('priority');
            $updateData['is_urgent'] = in_array($priority, ['P1', 'P3']);
            $updateData['is_important'] = in_array($priority, ['P1', 'P2']);
        }

        if ($request->get('assignee_id') !== null) {
            $updateData['assignee_id'] = $request->get('assignee_id');
        }

        $task->update($updateData);

        // Update Tag (Project) if provided
        if ($request->get('project') !== null) {
            $tag = Tag::where('name', 'LIKE', $request->get('project'))->first();
            if ($tag) {
                $task->syncTags([$tag->name]);
            }
        }

        // Reschedule for the assignee(s)
        dispatch(new ScheduleTasksForUser($task->assignee_id));
        if ($request->get('assignee_id') !== null && $request->get('assignee_id') != $oldAssigneeId) {
            dispatch(new ScheduleTasksForUser($oldAssigneeId));
            $task->notifyNewAssignment(auth()->id());
        }

        return Response::json([
            'status' => 'success',
            'message' => 'Task updated successfully.',
            'task' => $task->toArray(),
        ]);
    }
}
