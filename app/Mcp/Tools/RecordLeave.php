<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\UserLeave;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Record Leave')]
class RecordLeave extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Record a leave (unavailability) for Daksh. Reschedules tasks automatically.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('from_date', 'Start date (YYYY-MM-DD)')
            ->string('to_date', 'End date (YYYY-MM-DD)')
            ->boolean('half_day', 'Is this a half-day leave?')
            ->string('description', 'Reason or description for the leave')
            ->integer('user_id', 'The ID of the user to record leave for (Defaults to 1)');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $userId = $arguments['user_id'] ?? 1;

        $leave = UserLeave::create([
            'user_id' => $userId,
            'from_date' => $arguments['from_date'],
            'to_date' => $arguments['to_date'],
            'half_day' => $arguments['half_day'] ?? false,
            'reason' => $arguments['description'] ?? 'Added via MCP',
            'status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by_user_id' => 1,
        ]);

        dispatch(new ScheduleTasksForUser($userId));

        return ToolResult::json([
            'status' => 'success',
            'message' => "Leave recorded from {$leave->from_date->format('Y-m-d')} to {$leave->to_date->format('Y-m-d')}.",
        ]);
    }
}
