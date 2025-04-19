<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Timesheet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalaryDetails extends BaseWidget
{
    public $user;
    public $filterData;

    protected function getStats(): array
    {
        $widgets = [];

        $leaves =  $this->user->leaves()
            ->where('status', 'APPROVED')
            ->whereIn('code', ['CL', 'SL'])
            ->whereDate('from_date', '>=', $this->filterData['startDate'])
            ->whereDate('to_date', '<=', $this->filterData['endDate'])
            ->get();

        $leavesCount = $leaves->map(function ($leave) {
            return $leave->leave_days;
        })->sum();

        // TODO: Allowed leavs formula to change?
        $allowedLeaves = config('settings.monthly_allowed_leaves');

        $widgets[] = (new Stat('Leaves', $leavesCount))
            ->description($allowedLeaves . ' monthly leave allowed. This includes CL/SL both');

        // Salary Count
        if ($this->user->salary_type == 'monthly') {

            $workingDays = config('settings.working_days');
            $payableDays = $workingDays - (($leavesCount > 0) ? ($leavesCount - $allowedLeaves) : 0);

            $payableSalary = ($this->user->salary / $workingDays) * $payableDays;

            $widgets[] = (new Stat('Payable Days', $payableDays))
                ->description('Out of ' . $workingDays . ' working days');

            $widgets[] = new Stat('Payable Salary', sprintf("%.2f", $payableSalary));
        } else {
            $timeWorked = Timesheet::select('user_id', \DB::raw('SUM(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS time'))
                ->whereNotNull('start_at')
                ->whereNotNull('end_at')
                ->where('end_at', '>=', $this->filterData['startDate'])
                ->where('end_at', '<=', $this->filterData['endDate'])
                ->where('user_id', $this->user->id)
                ->groupBy('user_id')
                ->first();

            $timeWorked = ($timeWorked->time / 60);

            $payableSalary = $timeWorked * $this->user->salary;

            $widgets[] = new Stat('Payable Salary', sprintf("%.2f", $payableSalary));
        }

        return $widgets;
    }
}
