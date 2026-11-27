<?php

namespace Tests\Unit;

use App\Mcp\Tools\GetTask;
use App\Mcp\Tools\SyncTasks;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Mcp\Request;
use Tests\TestCase;

class SyncTasksTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    private function payload($response): array
    {
        return json_decode((string) $response->content(), true);
    }

    private function tagNames(array $tags): array
    {
        return collect($tags)->pluck('name')->sort()->values()->all();
    }

    private function ownTags(int $taskId): array
    {
        return Tag::payload(Task::findOrFail($taskId)->tags);
    }

    private function sync(array $arguments): array
    {
        return $this->payload((new SyncTasks)->handle(new Request($arguments + [
            'task' => 'Quote builder',
            'project' => 'Hiteshbhai-Quote-App',
            'priority' => 'P2',
            'estimated_minutes' => 60,
        ])));
    }

    public function test_it_creates_a_task_with_a_client_tag_and_a_board_tag(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Tag::findOrCreate('Hiteshbhai-Quote-App', null);
        Tag::findOrCreate('board-quote-app', null);

        $payload = $this->sync(['tags' => ['board-quote-app']]);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(
            ['Hiteshbhai-Quote-App', 'board-quote-app'],
            $this->tagNames($payload['tags'])
        );
        $this->assertSame(
            ['Hiteshbhai-Quote-App', 'board-quote-app'],
            $this->tagNames($this->ownTags($payload['timepro_task_id']))
        );

        $getTask = $this->payload((new GetTask)->handle(
            new Request(['timepro_task_id' => $payload['timepro_task_id']])
        ));

        $this->assertSame(
            ['Hiteshbhai-Quote-App', 'board-quote-app'],
            $this->tagNames($getTask['task']['tags'])
        );
    }

    public function test_it_fails_loudly_on_an_unknown_tag_and_writes_nothing(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Tag::findOrCreate('Hiteshbhai-Quote-App', null);
        Tag::findOrCreate('board-quote-app', null);

        $payload = $this->sync(['task' => 'Should not exist', 'tags' => ['board-quote-app', 'nope-not-a-tag']]);

        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsString('nope-not-a-tag', $payload['message']);
        $this->assertStringNotContainsString('board-quote-app', $payload['message']);
        $this->assertNull(Task::where('title', 'Should not exist')->first());
    }

    public function test_without_tags_it_keeps_todays_single_project_tag_behaviour(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Tag::findOrCreate('Hiteshbhai-Quote-App', null);

        $payload = $this->sync([]);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(['Hiteshbhai-Quote-App'], $this->tagNames($payload['tags']));
    }

    public function test_updating_a_task_replaces_its_extra_tags_with_the_full_set(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Tag::findOrCreate('Hiteshbhai-Quote-App', null);
        Tag::findOrCreate('board-quote-app', null);
        Tag::findOrCreate('board-sales', null);

        $taskId = $this->sync(['tags' => ['board-quote-app', 'board-sales']])['timepro_task_id'];

        $payload = $this->sync(['timepro_task_id' => $taskId, 'tags' => ['board-quote-app']]);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(
            ['Hiteshbhai-Quote-App', 'board-quote-app'],
            $this->tagNames($this->ownTags($taskId))
        );

        // An empty list removes every extra tag, leaving the client tag.
        $this->sync(['timepro_task_id' => $taskId, 'tags' => []]);

        $this->assertSame(['Hiteshbhai-Quote-App'], $this->tagNames($this->ownTags($taskId)));
    }

    public function test_it_deduplicates_the_project_tag_when_it_is_repeated_in_tags(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Tag::findOrCreate('Hiteshbhai-Quote-App', null);

        $payload = $this->sync(['tags' => ['Hiteshbhai-Quote-App']]);

        $this->assertSame(['Hiteshbhai-Quote-App'], $this->tagNames($this->ownTags($payload['timepro_task_id'])));
    }

    public function test_a_team_member_cannot_retag_another_members_task(): void
    {
        User::factory()->create();          // id 1 = admin
        $member = User::factory()->create();
        $other = User::factory()->create();

        Tag::findOrCreate('Hiteshbhai-Quote-App', null);
        Tag::findOrCreate('board-quote-app', null);

        $otherTask = Task::create(['title' => 'Theirs', 'assignee_id' => $other->id]);
        $otherTask->syncTags([Tag::findFromStringOfAnyType('Hiteshbhai-Quote-App')->first()]);

        $this->actingAs($member);

        $payload = $this->sync([
            'timepro_task_id' => $otherTask->id,
            'assignee_id' => $member->id,
            'tags' => ['board-quote-app'],
        ]);

        $this->assertSame('error', $payload['status']);
        $this->assertSame(
            ['Hiteshbhai-Quote-App'],
            $this->tagNames($this->ownTags($otherTask->id))
        );
    }
}
