<?php

namespace App\Support;

use Carbon\Carbon;

class FinancialYear
{
    /**
     * The calendar year the financial year starts in.
     *
     * The financial year runs April - March, so January to March belong to the
     * previous calendar year (e.g. 2026-02-15 is part of FY 2025-26).
     */
    public static function startYear(Carbon|string $date): int
    {
        $date = Carbon::parse($date);

        return $date->month >= 4 ? $date->year : $date->year - 1;
    }

    public static function label(Carbon|string $date): string
    {
        $startYear = self::startYear($date);

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }

    public static function startsOn(Carbon|string $date): Carbon
    {
        return Carbon::create(self::startYear($date), 4, 1)->startOfDay();
    }
}
