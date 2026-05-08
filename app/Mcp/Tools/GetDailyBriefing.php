<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Get Daily Briefing')]
class GetDailyBriefing extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Get a summary of tasks scheduled for today and any overdue high-priority items.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema;
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $today = now()->format('Y-m-d');

        $tasks = Task::where('due_date', $today)
            ->whereNull('completed_at')
            ->get();

        $format = function ($tasks) {
            return $tasks->map(function ($t) {
                return [
                    'task_id' => $t->id,
                    'title' => $t->title,
                    'estimate' => $t->estimate,
                    'project' => $t->tag,
                ];
            });
        };

        $grouped = $tasks->groupBy('assignee_id')->map(function ($userTasks) use ($format) {
            return $format($userTasks);
        });

        return ToolResult::json([
            'today' => $grouped->toArray(),
        ]);
    }
}
