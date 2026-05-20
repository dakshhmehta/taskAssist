<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Carbon\Carbon;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('List Tasks')]
class ListTasks extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'List tasks for a specific user, filtered by a maximum due date and optional limit.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('user_id', 'The ID of the user to list tasks for')->required()
            ->integer('limit', 'The limit number of tasks to return (defaults to -1 for no limit)')
            ->string('till_date', 'The date (Y-m-d format) to list tasks up to (defaults to today)');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $userId = $arguments['user_id'];
        $limit = $arguments['limit'] ?? -1;

        try {
            $tillDate = isset($arguments['till_date']) 
                ? Carbon::parse($arguments['till_date'])->endOfDay() 
                : now()->endOfDay();
        } catch (\Exception $e) {
            return ToolResult::error("Invalid till_date format. Please use Y-m-d format.");
        }

        $query = Task::where('assignee_id', $userId)
            ->whereNull('completed_at')
            ->where('due_date', '<=', $tillDate)
            ->orderBy('due_date', 'ASC');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $tasks = $query->get();

        return ToolResult::json([
            'status' => 'success',
            'tasks' => $tasks->map(fn($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date,
                'estimate' => $task->estimate,
                'is_urgent' => $task->is_urgent,
                'is_important' => $task->is_important,
                'project' => $task->tag,
            ])->toArray(),
        ]);
    }
}
