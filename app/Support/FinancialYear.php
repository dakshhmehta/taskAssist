<?php

namespace App\Support;

use Carbon\Carbon;

class FinancialYear
{
    public static function label(Carbon|string $date): string
    {
        $date = Carbon::parse($date);
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }

    public static function startsOn(Carbon|string $date): Carbon
    {
        $date = Carbon::parse($date);
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return Carbon::create($startYear, 4, 1)->startOfDay();
    }
}
