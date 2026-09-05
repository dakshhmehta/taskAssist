<?php

namespace Tests\Unit;

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Mcp\Tools\AddTask;
use App\Mcp\Tools\UpdateTask;
use App\Notifications\NewTaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_new_assignment_notifies_other_user_but_not_self(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $task = Task::create(['title' => 'Assigned to other', 'assignee_id' => $assignee->id]);

        $task->notifyNewAssignment($actor->id);

        Notification::assertSentTo($assignee, NewTaskAssignedNotification::class);
        Notification::assertNotSentTo($actor, NewTaskAssignedNotification::class);

        // Self-assignment must not notify.
        Notification::fake();
        $selfTask = Task::create(['title' => 'Assigned to self', 'assignee_id' => $actor->id]);
        $selfTask->notifyNewAssignment($actor->id);

        Notification::assertNotSentTo($actor, NewTaskAssignedNotification::class);
    }

    public function test_add_task_sends_notification_to_assignee_over_mcp(): void
    {
        Notification::fake();
        Bus::fake();

        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        Tag::create(['name' => 'taskAssist']);

        $this->actingAs($actor);

        $result = (new AddTask)->handle([
            'task' => 'MCP created task',
            'project' => 'taskAssist',
            'priority' => 'P2',
            'estimated_minutes' => 60,
            'assignee_id' => $assignee->id,
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);
        $this->assertSame('success', $payload['status']);

        Notification::assertSentTo($assignee, NewTaskAssignedNotification::class);
        Notification::assertNotSentTo($actor, NewTaskAssignedNotification::class);
    }

    public function test_add_task_does_not_notify_when_assignee_is_the_actor(): void
    {
        Notification::fake();
        Bus::fake();

        $actor = User::factory()->create();
        Tag::create(['name' => 'taskAssist']);

        $this->actingAs($actor);

        $result = (new AddTask)->handle([
            'task' => 'MCP self-assigned task',
            'project' => 'taskAssist',
            'priority' => 'P2',
            'estimated_minutes' => 60,
            'assignee_id' => $actor->id,
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);
        $this->assertSame('success', $payload['status']);

        Notification::assertNotSentTo($actor, NewTaskAssignedNotification::class);
    }

    public function test_update_task_sends_notification_on_reassign_over_mcp(): void
    {
        Notification::fake();
        Bus::fake();

        $actor = User::factory()->create();
        $oldAssignee = User::factory()->create();
        $newAssignee = User::factory()->create();
        $task = Task::create(['title' => 'Reassigned task', 'assignee_id' => $oldAssignee->id]);

        $this->actingAs($actor);

        $result = (new UpdateTask)->handle([
            'task_id' => $task->id,
            'assignee_id' => $newAssignee->id,
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);
        $this->assertSame('success', $payload['status']);

        Notification::assertSentTo($newAssignee, NewTaskAssignedNotification::class);
        Notification::assertNotSentTo($oldAssignee, NewTaskAssignedNotification::class);
    }

    public function test_update_task_does_not_notify_when_assignee_unchanged(): void
    {
        Notification::fake();
        Bus::fake();

        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $task = Task::create(['title' => 'Unchanged assignee', 'assignee_id' => $assignee->id]);

        $this->actingAs($actor);

        $result = (new UpdateTask)->handle([
            'task_id' => $task->id,
            'task' => 'Only title changed',
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);
        $this->assertSame('success', $payload['status']);

        Notification::assertNotSentTo($assignee, NewTaskAssignedNotification::class);
    }

    public function test_mass_edit_reassigns_and_notifies_new_assignee(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $oldAssignee = User::factory()->create();
        $newAssignee = User::factory()->create();
        $task = Task::create([
            'title' => 'Bulk reassigned',
            'assignee_id' => $oldAssignee->id,
            'is_urgent' => false,
            'is_important' => true,
        ]);

        $task->applyMassEdit([
            'assignee_id' => $newAssignee->id,
            'is_urgent' => true,
            'is_important' => false,
        ], $actor->id);

        $this->assertSame($newAssignee->id, $task->fresh()->assignee_id);
        $this->assertTrue($task->fresh()->is_urgent);
        $this->assertFalse($task->fresh()->is_important);

        Notification::assertSentTo($newAssignee, NewTaskAssignedNotification::class);
        Notification::assertNotSentTo($oldAssignee, NewTaskAssignedNotification::class);
    }

    public function test_mass_edit_without_assignee_change_does_not_notify(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $task = Task::create([
            'title' => 'Only flags changed',
            'assignee_id' => $assignee->id,
            'is_urgent' => false,
            'is_important' => false,
        ]);

        $task->applyMassEdit([
            'is_urgent' => true,
            'is_important' => true,
        ], $actor->id);

        Notification::assertNotSentTo($assignee, NewTaskAssignedNotification::class);
    }
}
