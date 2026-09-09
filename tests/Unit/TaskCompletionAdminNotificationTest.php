<?php

namespace Tests\Unit;

use App\Mcp\Tools\CompleteTask;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskCompletionAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_notified_when_a_team_member_completes_their_task(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $member = User::factory()->create();
        $task = Task::create(['title' => 'Team task', 'assignee_id' => $member->id]);

        $this->actingAs($member);
        $task->complete();

        Notification::assertSentTo($admin, TaskCompletedNotification::class);
        Notification::assertNotSentTo($member, TaskCompletedNotification::class);
    }

    public function test_assignee_is_notified_when_admin_completes_the_task_via_mcp(): void
    {
        Bus::fake();
        Notification::fake();

        $admin = User::factory()->create();
        $member = User::factory()->create();
        $task = Task::create(['title' => 'Admin closed task', 'assignee_id' => $member->id]);

        $this->actingAs($admin);
        $result = (new CompleteTask)->handle(['timepro_task_id' => $task->id]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        Notification::assertSentTo($member, TaskCompletedNotification::class);
        Notification::assertNotSentTo($admin, TaskCompletedNotification::class);
    }

    public function test_admin_is_notified_when_assignee_completes_via_mcp(): void
    {
        Bus::fake();
        Notification::fake();

        $admin = User::factory()->create();
        $member = User::factory()->create();
        $task = Task::create(['title' => 'Member closed task', 'assignee_id' => $member->id]);

        $this->actingAs($member);
        $result = (new CompleteTask)->handle(['timepro_task_id' => $task->id]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        Notification::assertSentTo($admin, TaskCompletedNotification::class);
    }
}
