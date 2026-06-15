<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('List Team Members')]
class ListTeamMembers extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Get a list of all team members and their IDs to use as assignee_id.';
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
        $users = User::query()
            ->when(! Auth::user()?->is_admin, fn($q) => $q->where('is_disabled', false))
            ->get(['id', 'name', 'email']);

        return ToolResult::json($users->toArray());
    }
}
