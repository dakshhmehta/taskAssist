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

#[Title('Update Task')]
class UpdateTask extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Update an existing task in the database.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('task_id', 'The ID of the task to update')->required()
            ->string('task', 'The new title of the task')
            ->string('project', 'The new project/tag name to search for')
            ->string('priority', 'New Priority (P1, P2, P3, P4)')
            ->integer('estimated_minutes', 'New estimated time in minutes')
            ->integer('assignee_id', 'The new ID of the user to assign the task to');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $task = Task::find($arguments['task_id']);

        if (!$task) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Task with ID {$arguments['task_id']} not found.",
            ]);
        }

        $oldAssigneeId = $task->assignee_id;
        $updateData = [];

        if (isset($arguments['task'])) {
            $updateData['title'] = $arguments['task'];
        }

        if (isset($arguments['estimated_minutes'])) {
            $updateData['estimate'] = $arguments['estimated_minutes'];
        }

        if (isset($arguments['priority'])) {
            $priority = $arguments['priority'];
            $updateData['is_urgent'] = in_array($priority, ['P1', 'P3']);
            $updateData['is_important'] = in_array($priority, ['P1', 'P2']);
        }

        if (isset($arguments['assignee_id'])) {
            $updateData['assignee_id'] = $arguments['assignee_id'];
        }

        $task->update($updateData);

        // Update Tag (Project) if provided
        if (isset($arguments['project'])) {
            $tag = Tag::where('name', 'LIKE', '%' . $arguments['project'] . '%')->first();
            if ($tag) {
                $task->syncTags([$tag->name]);
            }
        }

        // Reschedule for the assignee(s)
        dispatch(new ScheduleTasksForUser($task->assignee_id));
        if (isset($arguments['assignee_id']) && $arguments['assignee_id'] != $oldAssigneeId) {
            dispatch(new ScheduleTasksForUser($oldAssigneeId));
        }

        return ToolResult::json([
            'status' => 'success',
            'message' => 'Task updated successfully.',
            'task' => $task->toArray(),
        ]);
    }
}
