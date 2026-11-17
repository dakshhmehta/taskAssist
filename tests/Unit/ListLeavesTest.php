<?php

namespace Tests\Unit;

use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\ListLeaves;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Mcp\Request;
use Tests\TestCase;

class ListLeavesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
    }

    private function leave(User $user, array $attributes = []): UserLeave
    {
        return UserLeave::create(array_merge([
            'user_id' => $user->id,
            'from_date' => now()->addDays(10)->toDateString(),
            'to_date' => now()->addDays(11)->toDateString(),
            'code' => 'SL',
            'status' => 'APPROVED',
            'remarks' => 'Family function',
        ], $attributes));
    }

    public function test_tool_is_registered_on_the_task_server(): void
    {
        $this->assertContains(ListLeaves::class, $this->mcpServerToolClasses(TaskServer::class));
    }

    public function test_non_admin_only_sees_their_own_leaves(): void
    {
        $employee = User::factory()->create(['id' => 2]);
        $other = User::factory()->create(['id' => 3]);

        $own = $this->leave($employee);
        $this->leave($other);

        $this->actingAs($employee);

        $response = json_decode((string) (new ListLeaves)->handle(new Request([
            'user_id' => $other->id,
        ]))->content(), true);

        $this->assertEquals('success', $response['status']);
        $this->assertCount(1, $response['leaves']);
        $this->assertEquals($own->id, $response['leaves'][0]['id']);
        $this->assertEquals($employee->id, $response['leaves'][0]['user_id']);
    }

    public function test_admin_can_filter_by_user_code_and_status(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $employee = User::factory()->create(['id' => 2]);
        $other = User::factory()->create(['id' => 3]);

        $cl = $this->leave($employee, ['code' => 'CL', 'status' => 'APPROVED']);
        $this->leave($employee, ['code' => 'PL', 'status' => 'NEW']);
        $this->leave($other, ['code' => 'CL', 'status' => 'APPROVED']);

        $this->actingAs($admin);

        $response = json_decode((string) (new ListLeaves)->handle(new Request([
            'user_id' => $employee->id,
            'code' => 'CL',
            'status' => 'APPROVED',
        ]))->content(), true);

        $this->assertCount(1, $response['leaves']);
        $this->assertEquals($cl->id, $response['leaves'][0]['id']);
        $this->assertEquals('CL', $response['leaves'][0]['code']);
    }

    public function test_admin_can_list_all_users_and_pagination_is_reported(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $employee = User::factory()->create(['id' => 2]);

        foreach (range(1, 3) as $i) {
            $this->leave($employee, ['remarks' => "Leave {$i}"]);
        }

        $this->actingAs($admin);

        $response = json_decode((string) (new ListLeaves)->handle(new Request([
            'all_users' => true,
            'per_page' => 2,
            'page' => 1,
        ]))->content(), true);

        $this->assertCount(2, $response['leaves']);
        $this->assertEquals(3, $response['pagination']['total']);
        $this->assertEquals(2, $response['pagination']['last_page']);
        $this->assertTrue($response['pagination']['has_more']);
        $this->assertNotEmpty($response['leave_types']);
    }

    public function test_leaves_are_ordered_by_from_date_descending_and_expose_resource_fields(): void
    {
        $employee = User::factory()->create(['id' => 2]);

        $this->leave($employee, ['from_date' => '2026-06-01', 'to_date' => '2026-06-02']);
        $newer = $this->leave($employee, ['from_date' => '2026-07-01', 'to_date' => '2026-07-01', 'half_day' => true]);

        $this->actingAs($employee);

        $response = json_decode((string) (new ListLeaves)->handle(new Request([]))->content(), true);

        $this->assertEquals($newer->id, $response['leaves'][0]['id']);
        $this->assertTrue($response['leaves'][0]['half_day']);
        $this->assertEquals(0.5, $response['leaves'][0]['leave_days']);
        $this->assertEquals('2026-07-01', $response['leaves'][0]['from_date']);
        $this->assertArrayHasKey('admin_remarks', $response['leaves'][0]);
        $this->assertEquals($employee->id, $response['balance']['user_id']);
    }
}
