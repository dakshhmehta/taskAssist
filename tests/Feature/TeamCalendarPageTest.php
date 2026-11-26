<?php

namespace Tests\Feature;

use App\Filament\Pages\TeamCalendar;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamCalendarPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_opens_on_the_current_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 10:00:00'));

        $this->actingAs(User::factory()->create());

        Livewire::test(TeamCalendar::class)
            ->assertSet('month', '2026-11')
            ->assertSee('November 2026');
    }

    public function test_it_shows_holidays_and_absent_members(): void
    {
        $viewer = User::factory()->create();
        $absent = User::factory()->create(['name' => 'Kaushal']);

        Holiday::create(['date' => '2026-11-10', 'occasion' => 'Diwali']);

        UserLeave::withoutEvents(fn () => UserLeave::create([
            'user_id' => $absent->id,
            'from_date' => '2026-11-10',
            'to_date' => '2026-11-11',
            'code' => 'CL',
            'status' => 'APPROVED',
        ]));

        $this->actingAs($viewer);

        Livewire::test(TeamCalendar::class)
            ->set('month', '2026-11')
            ->assertSee('Diwali')
            ->assertSee('Kaushal');
    }

    public function test_the_month_picker_changes_the_calendar(): void
    {
        $viewer = User::factory()->create();

        Holiday::create(['date' => '2026-11-10', 'occasion' => 'Diwali']);

        $this->actingAs($viewer);

        Livewire::test(TeamCalendar::class)
            ->set('month', '2026-12')
            ->assertSee('December 2026')
            ->assertDontSee('Diwali');
    }

    public function test_team_members_can_open_the_page(): void
    {
        config(['app.env' => 'local']);

        $this->actingAs(User::factory()->create(['id' => 5]))
            ->get('/admin/team-calendar')
            ->assertOk()
            ->assertSee('Team Calendar');
    }
}
