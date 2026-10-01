<?php

namespace Tests\Unit;

use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\AddTaskComment;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddTaskCommentTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(User $assignee, string $title = 'Squarespace OTP update'): Task
    {
        return Task::create([
            'title' => $title,
            'assignee_id' => $assignee->id,
        ]);
    }

    public function test_it_adds_a_comment_to_the_task_for_the_acting_user(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask($user);

        $this->actingAs($user);

        $result = (new AddTaskComment)->handle([
            'timepro_task_id' => $task->id,
            'comment' => 'OTP update verified on #8604.',
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame($task->id, $payload['task_id']);
        $this->assertSame('OTP update verified on #8604.', $payload['comment']['comment']);

        $comment = $task->fresh()->filamentComments()->latest()->first();
        $this->assertNotNull($comment);
        $this->assertSame('OTP update verified on #8604.', $comment->comment);
        $this->assertSame($user->id, $comment->user_id);
    }

    public function test_it_returns_error_for_an_unknown_task(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $result = (new AddTaskComment)->handle([
            'timepro_task_id' => 999999,
            'comment' => 'This task does not exist.',
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('error', $payload['status']);
        $this->assertSame(0, Comment::count());
    }

    public function test_it_returns_error_for_an_empty_comment(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask($user);

        $this->actingAs($user);

        $result = (new AddTaskComment)->handle([
            'timepro_task_id' => $task->id,
            'comment' => '   ',
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('error', $payload['status']);
        $this->assertSame(0, $task->fresh()->filamentComments()->count());
    }

    public function test_task_server_exposes_the_add_task_comment_tool(): void
    {
        $server = new TaskServer;

        $toolNames = collect($server->tools)
            ->map(fn (string $tool) => (new $tool)->name())
            ->all();

        $this->assertContains('add-task-comment', $toolNames);
    }
}
