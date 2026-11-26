<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\UserLeave;
use Carbon\Carbon;

class TeamCalendar
{
    /**
     * Build one month of the team calendar: days, holidays and absent members.
     *
     * A member is absent only when an approved leave request covers the day.
     */
    public function build(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $holidays = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get();

        $holidayByDate = $holidays->keyBy(
            fn (Holiday $holiday) => Carbon::parse($holiday->date)->toDateString()
        );

        $leaves = UserLeave::query()
            ->with('user')
            ->where('status', 'APPROVED')
            ->where('from_date', '<=', $end->toDateString())
            ->where('to_date', '>=', $start->toDateString())
            ->get()
            ->filter(fn (UserLeave $leave) => $leave->user && ! $leave->user->is_disabled);

        $photos = $leaves
            ->mapWithKeys(fn (UserLeave $leave) => [$leave->user->id => $leave->user->getGrayscaleAvatarUrl()]);

        $days = [];
        $absentDays = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $absent = $leaves
                ->filter(fn (UserLeave $leave) => $leave->from_date->lte($date) && $leave->to_date->gte($date))
                ->map(fn (UserLeave $leave) => [
                    'id' => $leave->user->id,
                    'name' => $leave->user->name,
                    'photo' => $photos[$leave->user->id],
                    'code' => $leave->code,
                ])
                ->values()
                ->all();

            $holiday = $holidayByDate->get($date->toDateString());
            $isSunday = $date->isSunday();

            $days[] = [
                'date' => $date->toDateString(),
                'day' => $date->day,
                'is_sunday' => $isSunday,
                'holiday' => $holiday?->occasion,
                'absent' => $absent,
                'status' => match (true) {
                    $holiday !== null => 'holiday',
                    $isSunday => 'sunday',
                    $absent !== [] => 'leave',
                    default => null,
                },
            ];

            if ($absent !== []) {
                $absentDays++;
            }
        }

        $cells = array_merge(array_fill(0, $start->dayOfWeek, null), $days);

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return [
            'month' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'weeks' => array_chunk($cells, 7),
            'absent_days' => $absentDays,
        ];
    }
}
