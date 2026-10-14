<?php

namespace App\Mcp\Tools;

use App\Jobs\ScheduleTasksForUser;
use App\Models\UserLeave;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class RecordLeave extends Tool
{
    protected string $title = 'Record Leave';

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
    public function schema(JsonSchema $schema): array
    {
        return [
            'from_date' => $schema->string()->description('Start date (YYYY-MM-DD)'),
            'to_date' => $schema->string()->description('End date (YYYY-MM-DD)'),
            'half_day' => $schema->boolean()->description('Is this a half-day leave?'),
            'description' => $schema->string()->description('Reason or description for the leave'),
            'user_id' => $schema->integer()->description('The ID of the user to record leave for (Defaults to 1)'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $userId = $request->get('user_id') ?? 1;

        $leave = UserLeave::create([
            'user_id' => $userId,
            'from_date' => $request->get('from_date'),
            'to_date' => $request->get('to_date'),
            'half_day' => $request->get('half_day') ?? false,
            'reason' => $request->get('description') ?? 'Added via MCP',
            'status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by_user_id' => 1,
        ]);

        dispatch(new ScheduleTasksForUser($userId));

        return Response::json([
            'status' => 'success',
            'message' => "Leave recorded from {$leave->from_date->format('Y-m-d')} to {$leave->to_date->format('Y-m-d')}.",
        ]);
    }
}
