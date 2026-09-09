<?php

namespace Tests\Unit;

use App\Mcp\Tools\ListNotifications;
use App\Mcp\Servers\ResellerServer;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NewTaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_unread_notifications_for_active_users_by_default_with_their_related_models(): void
    {
        Carbon::setTestNow('2026-09-05 10:00:00');

        $activeUser = User::factory()->create(['name' => 'Active User']);
        $disabledUser = User::factory()->create(['is_disabled' => true]);
        $task = Task::create(['title' => 'Review notifications', 'assignee_id' => $activeUser->id]);

        $activeUser->notify(new NewTaskAssignedNotification($task));

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => NewTaskAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $activeUser->id,
            'data' => ['message' => 'Already read'],
            'read_at' => now(),
        ]);

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => NewTaskAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $disabledUser->id,
            'data' => ['message' => 'Disabled user notification'],
        ]);

        $result = (new ListNotifications)->handle([]);
        $response = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $response['status']);
        $this->assertCount(1, $response['notifications']);
        $this->assertSame($activeUser->id, $response['notifications'][0]['user']['id']);
        $this->assertSame($task->id, $response['notifications'][0]['task']['id']);
        $this->assertSame('task_assigned', $response['notifications'][0]['data']['event']);
    }

    public function test_it_applies_user_date_and_read_status_filters(): void
    {
        $user = User::factory()->create();

        $readNotification = $this->createNotification($user, '2026-09-02 10:00:00', '2026-09-02 12:00:00');
        $unreadNotification = $this->createNotification($user, '2026-09-03 10:00:00');
        $outsideDateRange = $this->createNotification($user, '2026-09-04 10:00:00');

        $result = (new ListNotifications)->handle([
            'user_id' => $user->id,
            'from_date' => '2026-09-02',
            'to_date' => '2026-09-03',
            'includes_read' => true,
        ]);
        $response = json_decode($result->toArray()['content'][0]['text'], true);
        $notificationIds = collect($response['notifications'])->pluck('id')->all();

        $this->assertContains($readNotification->id, $notificationIds);
        $this->assertContains($unreadNotification->id, $notificationIds);
        $this->assertNotContains($outsideDateRange->id, $notificationIds);
    }

    public function test_it_is_registered_with_the_mcp_server(): void
    {
        $this->assertContains(ListNotifications::class, (new ResellerServer)->tools);
    }

    public function test_it_returns_task_comment_and_completion_notifications_for_the_admin(): void
    {
        $admin = User::factory()->create();
        $assignee = User::factory()->create();
        $task = Task::create([
            'title' => 'Review notification events',
            'assignee_id' => $assignee->id,
        ]);

        $this->actingAs($assignee);

        $task->filamentComments()->create([
            'subject_type' => $task->getMorphClass(),
            'user_id' => $assignee->id,
            'comment' => 'The task needs a review.',
        ]);
        $task->complete();

        $result = (new ListNotifications)->handle([
            'user_id' => $admin->id,
            'includes_read' => true,
        ]);
        $response = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertCount(2, $response['notifications']);
        $this->assertEqualsCanonicalizing(
            ['task_commented', 'task_completed'],
            collect($response['notifications'])->pluck('data.event')->all()
        );

        foreach ($response['notifications'] as $notification) {
            $this->assertSame($task->id, $notification['data']['task_id']);
            $this->assertSame($assignee->id, $notification['data']['actor_id']);
            $this->assertSame($task->id, $notification['task']['id']);
        }
    }

    private function createNotification(User $user, string $createdAt, ?string $readAt = null): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => NewTaskAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Notification'],
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
