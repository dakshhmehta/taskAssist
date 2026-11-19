<?php

namespace App\Support;

use Carbon\Carbon;

class InvoiceTerm
{
    /**
     * Shift an expiry date forward by the given number of years.
     */
    public static function shift(?Carbon $date, int $years): ?Carbon
    {
        return $date?->copy()->addYears($years);
    }
}
