<?php

function AmountInWords(float $number = 0)
{
    $no = floor($number);
    $decimal = round($number - $no, 2) * 100;
    $decimalPart = $decimal;
    $hundred = null;
    $digitsLength = strlen($no);
    $decimalLength = strlen($decimal);
    $i = 0;
    $str = array();
    $str2 = array();
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two',
        3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
        7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
        13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
        70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');

    while ($i < $digitsLength) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? '' : null;
            $hundred = ($counter == 1 && $str[0]) ? 'and ' : null;
            $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
        } else {
            $str[] = null;
        }
    }

    $d = 0;
    while ($d < $decimalLength) {
        $divider = ($d == 2) ? 10 : 100;
        $decimal_number = floor($decimal % $divider);
        $decimal = floor($decimal / $divider);
        $d += $divider == 10 ? 1 : 2;
        if ($decimal_number) {
            $plurals = (($counter = count($str2)) && $decimal_number > 9) ? '' : null;
            $hundreds = ($counter == 1 && $str2[0]) ? ' and ' : null;
            @$str2[] = ($decimal_number < 21) ? $words[$decimal_number] . ' ' . $digits[$decimal_number] . $plural . ' ' . $hundred : $words[floor($decimal_number / 10) * 10] . ' ' . $words[$decimal_number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
        } else {
            $str2[] = null;
        }
    }

    $rupees = implode('', array_reverse($str));
    $paisa = implode('', array_reverse($str2));
    $paisa = ($decimalPart > 0) ?'and ' . $paisa . ' paise' : '';
    return $rupees . $paisa ."Only";
}

function IndianNumberingFormat($number, $precision = 2)
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


function currency($string, $prefix = '₹ ', $postfix = '/-')
{
    return $prefix . IndianNumberingFormat($string, 2) . $postfix;
}

function withQueryParams($array)
{
    return array_merge($array, request()->query());
}
