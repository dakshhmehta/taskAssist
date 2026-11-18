<?php

namespace Tests\Unit;

use App\Mcp\Servers\ResellerServer;
use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\CompleteTask;
use App\Mcp\Tools\GetTask;
use App\Mcp\Tools\ListLeaves;
use App\Mcp\Tools\ListNotifications;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\SyncTasks;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Tests\TestCase;

class McpTeamMemberRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User id 1 is the admin (User::getIsAdminAttribute).
     */
    private function admin(): User
    {
        return User::factory()->create();
    }

    private function member(): User
    {
        return User::factory()->create();
    }

    private function payload($response): array
    {
        return json_decode((string) $response->content(), true);
    }

    public function test_team_member_cannot_list_another_users_tasks(): void
    {
        $this->admin();
        $member = $this->member();
        $other = $this->member();

        $own = Task::create(['title' => 'Mine', 'assignee_id' => $member->id, 'due_date' => now()]);
        Task::create(['title' => 'Theirs', 'assignee_id' => $other->id, 'due_date' => now()]);

        $this->actingAs($member);

        $payload = $this->payload(
            (new ListTasks)->handle(new Request(['user_id' => $other->id]))
        );

        $this->assertSame('error', $payload['status']);
        $this->assertSame('Team members can only list their own tasks.', $payload['message']);

        $ownPayload = $this->payload((new ListTasks)->handle(new Request([])));

        $this->assertSame('success', $ownPayload['status']);
        $this->assertSame([$own->id], collect($ownPayload['tasks'])->pluck('id')->all());
    }

    public function test_admin_can_list_any_users_tasks(): void
    {
        $admin = $this->admin();
        $other = $this->member();

        Task::create(['title' => 'Theirs', 'assignee_id' => $other->id, 'due_date' => now()]);

        $this->actingAs($admin);

        $payload = $this->payload(
            (new ListTasks)->handle(new Request(['user_id' => $other->id]))
        );

        $this->assertSame('success', $payload['status']);
        $this->assertCount(1, $payload['tasks']);
    }

    public function test_team_member_can_only_get_their_own_assigned_task(): void
    {
        $this->admin();
        $member = $this->member();
        $other = $this->member();

        $ownTask = Task::create(['title' => 'Mine', 'assignee_id' => $member->id]);
        $otherTask = Task::create(['title' => 'Theirs', 'assignee_id' => $other->id]);

        $this->actingAs($member);

        $denied = $this->payload(
            (new GetTask)->handle(new Request(['timepro_task_id' => $otherTask->id]))
        );

        $this->assertSame('error', $denied['status']);
        $this->assertStringContainsString('not authorized', $denied['message']);

        $allowed = $this->payload(
            (new GetTask)->handle(new Request(['timepro_task_id' => $ownTask->id]))
        );

        $this->assertSame('success', $allowed['status']);
        $this->assertSame($ownTask->id, $allowed['task']['id']);
    }

    public function test_team_member_can_only_get_their_own_notifications(): void
    {
        $this->admin();
        $member = $this->member();
        $other = $this->member();

        $this->createNotificationFor($other);

        $this->actingAs($member);

        $denied = $this->payload(
            (new ListNotifications)->handle(new Request(['user_id' => $other->id]))
        );

        $this->assertSame('error', $denied['status']);

        $scoped = $this->payload((new ListNotifications)->handle(new Request([])));

        $this->assertSame('success', $scoped['status']);
        $this->assertCount(0, $scoped['notifications']);
    }

    public function test_team_member_only_sees_their_own_leaves(): void
    {
        $this->admin();
        $member = $this->member();
        $other = $this->member();

        $this->createLeave($member);
        $this->createLeave($other);

        $this->actingAs($member);

        $payload = $this->payload(
            (new ListLeaves)->handle(new Request(['user_id' => $other->id]))
        );

        $this->assertSame('success', $payload['status']);
        $this->assertCount(1, $payload['leaves']);
        $this->assertSame($member->id, $payload['leaves'][0]['user_id']);
    }

    public function test_team_member_can_only_sync_tasks_for_themselves(): void
    {
        Bus::fake();

        $this->admin();
        $member = $this->member();
        $other = $this->member();

        Tag::findOrCreate('ProjX', null);

        $this->actingAs($member);

        $denied = $this->payload((new SyncTasks)->handle(new Request([
            'task' => 'Sneaky assignment',
            'project' => 'ProjX',
            'priority' => 'P2',
            'estimated_minutes' => 60,
            'assignee_id' => $other->id,
        ])));

        $this->assertSame('error', $denied['status']);
        $this->assertNull(Task::where('title', 'Sneaky assignment')->first());

        $allowed = $this->payload((new SyncTasks)->handle(new Request([
            'task' => 'My own task',
            'project' => 'ProjX',
            'priority' => 'P2',
            'estimated_minutes' => 60,
        ])));

        $this->assertSame('success', $allowed['status']);
        $this->assertSame($member->id, Task::where('title', 'My own task')->first()->assignee_id);
    }

    public function test_team_member_can_only_complete_their_own_task(): void
    {
        Bus::fake();

        $this->admin();
        $member = $this->member();
        $other = $this->member();

        $otherTask = Task::create(['title' => 'Theirs', 'assignee_id' => $other->id]);
        $ownTask = Task::create(['title' => 'Mine', 'assignee_id' => $member->id]);

        $this->actingAs($member);

        $denied = $this->payload(
            (new CompleteTask)->handle(new Request(['timepro_task_id' => $otherTask->id]))
        );

        $this->assertSame('error', $denied['status']);
        $this->assertNull($otherTask->fresh()->completed_at);

        $allowed = $this->payload(
            (new CompleteTask)->handle(new Request(['timepro_task_id' => $ownTask->id]))
        );

        $this->assertSame('success', $allowed['status']);
        $this->assertNotNull($ownTask->fresh()->completed_at);
    }

    public function test_admin_can_complete_another_members_task(): void
    {
        Bus::fake();

        $admin = $this->admin();
        $other = $this->member();

        $task = Task::create(['title' => 'Theirs', 'assignee_id' => $other->id]);

        $this->actingAs($admin);

        $payload = $this->payload(
            (new CompleteTask)->handle(new Request(['timepro_task_id' => $task->id]))
        );

        $this->assertSame('success', $payload['status']);
    }

    public function test_timesheet_and_timer_tools_are_unavailable_to_team_members(): void
    {
        $this->admin();
        $member = $this->member();

        $this->actingAs($member);

        $memberTools = $this->serverToolNames();

        $this->assertNotContains('get-timesheet', $memberTools);
        $this->assertNotContains('set-timesheet', $memberTools);
        $this->assertNotContains('set-timer', $memberTools);
        $this->assertContains('list-tasks', $memberTools);
    }

    public function test_timesheet_and_timer_tools_stay_available_to_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        $adminTools = $this->serverToolNames();

        $this->assertContains('get-timesheet', $adminTools);
        $this->assertContains('set-timesheet', $adminTools);
        $this->assertContains('set-timer', $adminTools);
    }

    public function test_reseller_tools_are_unavailable_to_team_members(): void
    {
        $this->admin();
        $member = $this->member();

        $this->actingAs($member);

        $this->assertSame([], $this->serverToolNames(ResellerServer::class));
    }

    public function test_reseller_tools_stay_available_to_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        $this->assertContains('get-invoice', $this->serverToolNames(ResellerServer::class));
    }

    private function serverToolNames(string $server = TaskServer::class): array
    {
        $instance = (new \ReflectionClass($server))->newInstanceWithoutConstructor();

        return $instance->createContext()->tools()->map(fn ($tool) => $tool->name())->all();
    }

    private function createNotificationFor(User $user): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['task_id' => null],
            'read_at' => null,
        ]);
    }

    private function createLeave(User $user): UserLeave
    {
        return UserLeave::create([
            'user_id' => $user->id,
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(5)->toDateString(),
            'code' => 'SL',
            'remarks' => 'Not feeling well',
            'status' => 'APPROVED',
        ]);
    }
}
