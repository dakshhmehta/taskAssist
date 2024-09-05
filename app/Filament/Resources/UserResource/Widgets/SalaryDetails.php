<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalaryDetails extends BaseWidget
{
    public $user;
    public $filterData;

    protected function getStats(): array
    {
        $leaves =  $this->user->leaves()
            ->where('status', 'APPROVED')
            ->where('code', 'CL')
            ->whereDate('from_date', '>=', $this->filterData['startDate'])
            ->whereDate('to_date', '<=', $this->filterData['endDate'])
            ->get();

        $leavesCount = $leaves->map(function ($leave) {
            return $leave->leave_days;
        })->sum();

        // Salary Count
        $allowedLeaves = config('settings.monthly_allowed_leaves');

        $workingDays = config('settings.working_days');
        $payableDays = $workingDays - ($leavesCount - $allowedLeaves);

        $payableSalary = ($this->user->salary / $workingDays) * $payableDays;

        return [
            (new Stat('Leaves', $leavesCount))
                ->description($allowedLeaves.' monthly leave allowed'),
            (new Stat('Payable Days', $payableDays))
                ->description('Out of '.$workingDays.' working days'),
            new Stat('Payable Salary', sprintf("%.2f", $payableSalary)),
        ];
    }
}
