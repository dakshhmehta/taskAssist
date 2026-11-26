<?php

namespace Tests\Unit;

use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use App\Services\TeamCalendar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_month_grid_with_holidays_and_absences(): void
    {
        $onLeave = User::factory()->create(['name' => 'Kaushal', 'avatar_path' => 'avatars/kaushal.jpg']);
        $straddler = User::factory()->create(['name' => 'Vaibhavi']);
        $halfDay = User::factory()->create(['name' => 'Tanu']);
        $rejected = User::factory()->create(['name' => 'Raj']);

        Holiday::create(['date' => '2026-11-10', 'occasion' => 'Diwali']);

        $this->leave($onLeave, '2026-11-09', '2026-11-11');
        // Starts in October, still covers 1-2 November.
        $this->leave($straddler, '2026-10-30', '2026-11-02', 'PL');
        $this->leave($halfDay, '2026-11-20', '2026-11-20', halfDay: true);
        // Never approved, so the member is available.
        $this->leave($rejected, '2026-11-12', '2026-11-12', status: 'REJECTED');

        $calendar = (new TeamCalendar)->build(Carbon::parse('2026-11-01'));

        $this->assertSame('2026-11', $calendar['month']);
        $this->assertSame('November 2026', $calendar['label']);
        $this->assertCount(5, $calendar['weeks']);

        $days = collect($calendar['weeks'])->flatten(1)->filter()->keyBy('day');

        $this->assertSame('sunday', $days[1]['status']);
        $this->assertSame(['Vaibhavi'], array_column($days[1]['absent'], 'name'));
        $this->assertSame('leave', $days[9]['status']);
        $this->assertSame(['Kaushal'], array_column($days[9]['absent'], 'name'));
        $this->assertSame('CL', $days[9]['absent'][0]['code']);
        $this->assertSame('holiday', $days[10]['status']);
        $this->assertSame('Diwali', $days[10]['holiday']);
        $this->assertSame([], $days[12]['absent']);
        $this->assertNull($days[12]['status']);
        $this->assertSame(['Tanu'], array_column($days[20]['absent'], 'name'));

        // 30 November 2026 is the last day, so the final row is padded.
        $this->assertNull($calendar['weeks'][4][5]);
        $this->assertNull($calendar['weeks'][4][6]);

        $this->assertSame(6, $calendar['absent_days']);
    }

    public function test_absent_members_carry_their_grayscale_photo(): void
    {
        config(['filesystems.disks.public.url' => 'http://localhost/storage']);

        $user = User::factory()->create(['avatar_path' => 'avatars/kaushal.jpg']);

        $this->leave($user, '2026-11-05', '2026-11-05');

        $calendar = (new TeamCalendar)->build(Carbon::parse('2026-11-01'));

        $day = collect($calendar['weeks'])->flatten(1)->firstWhere('day', 5);

        $this->assertSame('http://localhost/storage/avatars/kaushal.jpg', $day['absent'][0]['photo']);
    }

    public function test_disabled_members_are_not_shown(): void
    {
        $user = User::factory()->create(['is_disabled' => true]);

        $this->leave($user, '2026-11-05', '2026-11-05');

        $calendar = (new TeamCalendar)->build(Carbon::parse('2026-11-01'));

        $day = collect($calendar['weeks'])->flatten(1)->firstWhere('day', 5);

        $this->assertSame([], $day['absent']);
    }

    private function leave(
        User $user,
        string $from,
        string $to,
        string $code = 'CL',
        string $status = 'APPROVED',
        bool $halfDay = false,
    ): void {
        // Leave model hooks validate CL advance notice and create CL debits;
        // the calendar only reads stored rows, so seed them directly.
        UserLeave::withoutEvents(fn () => UserLeave::create([
            'user_id' => $user->id,
            'from_date' => $from,
            'to_date' => $to,
            'code' => $code,
            'status' => $status,
            'half_day' => $halfDay,
        ]));
    }
}
