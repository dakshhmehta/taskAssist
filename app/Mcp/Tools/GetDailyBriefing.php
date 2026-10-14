<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetDailyBriefing extends Tool
{
    protected string $title = 'Get Daily Briefing';

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
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
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

        return Response::json([
            'today' => $grouped->toArray(),
        ]);
    }
}
