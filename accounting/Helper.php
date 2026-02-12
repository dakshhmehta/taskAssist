<?php

namespace Ri\Accounting;

class Helper
{
    public static function getPeriod($currentDate, $futureDate)
    {
        $months = $futureDate->diffInMonths($currentDate) + 1;
        $years = $futureDate->diffInYears($currentDate) + 1;

        if ($months == 0 and $years > 0) {
            if ($years == 1) {
                return $years . ' year';
            }

            return $years . ' year';
        }

        if ($months == 1) {
            return $months . ' month';
        }

        return $months . ' month';
    }

    public static function accountBalance($balance)
    {
        if ($balance > 0) { // 
            return static::indianNumberingFormat($balance, 2) . ' Dr.';
        }

        return static::indianNumberingFormat(abs($balance), 2) . ' Cr.';
    }

    public static function indianNumberingFormat($number, $precision = 2)
    {
        $number = round($number, $precision); // Ensure precision is maintained
        $decimalPart = substr(strrchr($number, "."), 1);

        if (strlen($decimalPart) < $precision) {
            $number = number_format($number, $precision, '.', '');
        }

        // Convert to Indian numbering format
        $explodedNumber = explode('.', $number);
        $integerPart = $explodedNumber[0];
        $decimalPart = isset($explodedNumber[1]) ? $explodedNumber[1] : '';

        // Format integer part in Indian numbering format
        $lastThree = substr($integerPart, -3);
        $otherNumbers = substr($integerPart, 0, -3);
        if ($otherNumbers != '') {
            $lastThree = ',' . $lastThree;
        }
        $integerPart = preg_replace("/\B(?=(\d{2})+(?!\d))/", ",", $otherNumbers) . $lastThree;

        return $integerPart . ($decimalPart ? '.' . $decimalPart : '');
    }
}
