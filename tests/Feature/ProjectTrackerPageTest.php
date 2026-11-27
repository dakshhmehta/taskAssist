<?php

namespace Tests\Feature;

use App\Filament\Pages\ProjectTracker;
use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectTrackerPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Filament only lets non-FilamentUser models through in the local env.
        config(['app.env' => 'local']);
    }

    public function test_admin_can_open_the_project_tracker()
    {
        $owner = User::factory()->create(['id' => 1]);

        $this->actingAs($owner)
            ->get('/admin/project-tracker')
            ->assertOk()
            ->assertSee('Team hours')
            ->assertSee('Hours per project')
            ->assertSee('Member roll-up')
            ->assertSee('Owner hours (included)');
    }

    public function test_non_admin_cannot_open_the_project_tracker()
    {
        $other = User::factory()->create(['id' => 2]);

        $this->actingAs($other);
        $this->assertFalse(ProjectTracker::canAccess());

        $this->get('/admin/project-tracker')->assertForbidden();
    }

    public function test_project_tracker_is_in_the_navigation_for_admins(): void
    {
        $owner = User::factory()->create(['id' => 1]);

        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Project Tracker');
    }

    public function test_project_tracker_is_not_in_the_navigation_for_non_admins(): void
    {
        $other = User::factory()->create(['id' => 2]);

        $this->actingAs($other)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Project Tracker');
    }

    public function test_page_lists_hours_for_the_selected_month()
    {
        $owner = User::factory()->create(['id' => 1]);
        $alice = User::factory()->create(['id' => 2]);

        $task = Task::create(['title' => 'Acme work', 'assignee_id' => $alice->id]);
        $task->attachTag('Acme');

        $start = Carbon::parse('2026-10-05 09:00:00');
        Timesheet::create([
            'user_id' => $alice->id,
            'task_id' => $task->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes(60),
        ]);

        // Owner (id 1) hours are counted by default.
        $ownerStart = Carbon::parse('2026-10-05 11:00:00');
        Timesheet::create([
            'user_id' => $owner->id,
            'task_id' => $task->id,
            'start_at' => $ownerStart,
            'end_at' => $ownerStart->copy()->addMinutes(30),
        ]);

        Livewire::actingAs($owner)
            ->test(ProjectTracker::class)
            ->set('month', '2026-10')
            ->assertSee('Include owner hours')
            ->assertSee('Acme')
            ->assertSee('1.5h')
            ->set('includeOwner', false)
            ->assertSee('1.0h')
            ->assertDontSee('1.5h');
    }
}
