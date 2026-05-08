<?php

namespace App\Mcp\Tools;

use App\Models\Tag;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('List Tags')]
class ListTags extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'List all available tags (projects) in the system.';
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
        $tags = Tag::orderBy('name', 'ASC')->get(['id', 'name']);

        return ToolResult::json([
            'tags' => $tags->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->toArray(),
        ]);
    }
}
