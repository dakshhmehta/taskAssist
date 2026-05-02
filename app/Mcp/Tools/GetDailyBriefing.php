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

        $todayTasks = Task::where('due_date', $today)
            ->whereNull('completed_at')
            ->get();

        $overdueP1 = Task::where('due_date', '<', $today)
            ->where('is_urgent', true)
            ->where('is_important', true)
            ->whereNull('completed_at')
            ->get();

        $format = function ($tasks) {
            return $tasks->map(function ($t) {
                return [
                    'timepro_task_id' => $t->id,
                    'title' => $t->title,
                    'estimate' => $t->estimate,
                    'project' => $t->tag,
                ];
            });
        };

        return ToolResult::json([
            'today' => $format($todayTasks),
            'overdue_p1' => $format($overdueP1),
        ]);
    }
}
