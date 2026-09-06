<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalaryDetails extends BaseWidget
{
    public $user;
    public $filterData;

    protected function getStats(): array
    {
        $widgets = [];

        $paymentDetails = $this->user->paymentDetailsForPeriod(
            Carbon::parse($this->filterData['startDate']),
            Carbon::parse($this->filterData['endDate']),
        );

        $widgets[] = (new Stat('Sick Leaves', $paymentDetails['sick_leave_days']));
        
        $userSalary = $this->user->salary;


        // Salary Count
        if ($this->user->salary_type == 'monthly') {
            $widgets[] = (new Stat('Payable Days', $paymentDetails['payable_days']))
                ->description('Out of ' . $paymentDetails['working_days'] . ' working days');

            if(auth()->user()->is_admin){
                $description = 'Total Salary = ' . $userSalary;
                if ($paymentDetails['effective_hourly_rate'] !== null) {
                    $description .= ', Eff. Hourly Rate = ' . sprintf('%.2f', $paymentDetails['effective_hourly_rate']);
                }

                $widgets[] = (new Stat('Payable Salary', sprintf("%.2f", $paymentDetails['net_payable_amount'])))
                    ->description($description);
            }
        } else {
            $widgets[] = (new Stat('Payable Salary', sprintf("%.2f", $paymentDetails['net_payable_amount'])))
                ->description('Hourly Rate = '.$userSalary);
        }

        return $widgets;
    }
}
