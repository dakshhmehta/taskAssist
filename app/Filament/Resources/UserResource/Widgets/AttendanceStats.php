<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Timesheet;
use App\Models\UserCheckIn;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttendanceStats extends BaseWidget
{
    public $user;
    public $filterData;

    protected function getStats(): array
    {
        // 1. Total Time Worked (from timesheet)
        $timeWorked = Timesheet::select(\DB::raw('SUM(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS time'))
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->where('start_at', '>=', $this->filterData['startDate'])
            ->where('end_at', '<=', $this->filterData['endDate'])
            ->where('user_id', $this->user->id)
            ->first();

        // 2. Total Office Working Hrs (from checkins data with matched check-out)
        $officeHours = \DB::table('user_checkins as a')
            ->select(\DB::raw('SUM(TIMESTAMPDIFF(MINUTE, a.punch_at, b.punch_at)) AS total_minutes'))
            ->join('user_checkins as b', function ($join) {
                $join->on('a.user_id', '=', 'b.user_id')
                    ->whereRaw('b.type = "OUT"')
                    ->whereRaw('b.punch_at > a.punch_at')
                    ->whereRaw('DATE(b.punch_at) = DATE(a.punch_at)')
                    ->whereRaw('b.id = (
                        SELECT MIN(c.id) 
                        FROM user_checkins c 
                        WHERE c.user_id = a.user_id 
                        AND c.type = "OUT" 
                        AND c.punch_at > a.punch_at 
                        AND DATE(c.punch_at) = DATE(a.punch_at)
                    )');
            })
            ->where('a.type', 'IN')
            ->where('a.user_id', $this->user->id)
            ->whereDate('a.punch_at', '>=', $this->filterData['startDate'])
            ->whereDate('a.punch_at', '<=', $this->filterData['endDate'])
            ->first();

        // 3. Avg % of Productivity (average of individual check-in session productivities)
        $checkins = UserCheckIn::select(
                'user_checkins.id',
                'user_checkins.user_id',
                'user_checkins.punch_at as check_in',
                \DB::raw('(SELECT b.punch_at 
                          FROM user_checkins b 
                          WHERE b.user_id = user_checkins.user_id 
                            AND b.type = "OUT" 
                            AND b.punch_at > user_checkins.punch_at 
                            AND DATE(b.punch_at) = DATE(user_checkins.punch_at)
                          ORDER BY b.punch_at ASC 
                          LIMIT 1) as check_out')
            )
            ->where('type', 'IN')
            ->where('user_id', $this->user->id)
            ->whereDate('punch_at', '>=', $this->filterData['startDate'])
            ->whereDate('punch_at', '<=', $this->filterData['endDate'])
            ->get();

        $productivitySum = 0;
        $productivityCount = 0;
        
        foreach ($checkins as $checkin) {
            if (!$checkin->check_out) {
                continue;
            }
            
            $totalCheckInMinutes = now()->parse($checkin->check_in)->diffInMinutes($checkin->check_out);
            
            if ($totalCheckInMinutes == 0) {
                continue;
            }
            
            $timesheets = Timesheet::where('user_id', $checkin->user_id)
                ->whereNotNull('end_at')
                ->where('start_at', '>=', $checkin->check_in)
                ->where('end_at', '<=', $checkin->check_out)
                ->get();
            
            $totalWorkMinutes = 0;
            foreach ($timesheets as $timesheet) {
                $totalWorkMinutes += $timesheet->end_at->diffInMinutes($timesheet->start_at);
            }
            
            $sessionProductivity = ($totalWorkMinutes / $totalCheckInMinutes) * 100;
            $productivitySum += $sessionProductivity;
            $productivityCount++;
        }
        
        $avgProductivity = $productivityCount > 0 ? ($productivitySum / $productivityCount) : null;

        // 4. Avg Late In Time after 10 am in first half of the day
        $lateCheckins = UserCheckIn::where('user_id', $this->user->id)
            ->where('type', 'IN')
            ->whereDate('punch_at', '>=', $this->filterData['startDate'])
            ->whereDate('punch_at', '<=', $this->filterData['endDate'])
            ->whereRaw('TIME(punch_at) > "10:00:00"')
            ->whereRaw('TIME(punch_at) < "14:00:00"')
            ->get();

        $avgLateMinutes = 0;
        if ($lateCheckins->count() > 0) {
            $totalLateMinutes = 0;
            foreach ($lateCheckins as $checkin) {
                $tenAM = $checkin->punch_at->copy()->setTime(10, 0, 0);
                $totalLateMinutes += $tenAM->diffInMinutes($checkin->punch_at);
            }
            $avgLateMinutes = $totalLateMinutes / $lateCheckins->count();
        }

        return [
            Stat::make('Total Time Worked', $timeWorked && $timeWorked->time ? Timesheet::toHMS($timeWorked->time) : '00:00'),
            Stat::make('Total Office Hours', $officeHours && $officeHours->total_minutes ? Timesheet::toHMS($officeHours->total_minutes) : '00:00'),
            Stat::make('Avg Productivity', $avgProductivity !== null ? number_format($avgProductivity, 2) . '%' : 'N/A'),
            Stat::make('Avg Late Check-in', $avgLateMinutes > 0 ? Timesheet::toHMS($avgLateMinutes) : '00:00')
                ->description('After 10 AM'),
        ];
    }
}
