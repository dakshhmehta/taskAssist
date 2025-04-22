<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $touches = ['client'];

    protected $casts = [
        'date' => 'date',
        'paid_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function extras()
    {
        return $this->hasMany(InvoiceExtra::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function getTotalAttribute()
    {
        return $this->items()->sum('price') + $this->extras()->sum('price');
    }

    public function scopeUnpaid($q)
    {
        return $q->whereNull('paid_date');
    }

    public function markAsPaid($date = null, $remarks = null)
    {
        $this->paid_date = $date;
        $this->payment_remarks = $remarks;

        $this->save();
    }

    public function inWords()
    {
        $no = floor($this->total);
        $point = round($this->total - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = array();
        $words = array(
            0 => '',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
            30 => 'thirty',
            40 => 'forty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety'
        );
        $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred
                    :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
            } else {
                $str[] = null;
            }
        }
        $str = array_reverse($str);
        $result = implode('', $str);
        $points = ($point) ? $words[$point / 10] . " " . $words[$point = $point % 10] : '';

        return $result . "" . ($points ? "and " . $points . " paise" : "only");
    }

    public static function nextInvoiceNumber()
    {
        // Define the prefix and the current year
        $prefix = 'DH-';
        $suffix = '\/2024';

        // Find the latest invoice number using the prefix and suffix
        $latestInvoice = self::where('invoice_no', 'LIKE', "{$prefix}%{$suffix}")
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($latestInvoice) {
            // Extract the number from the latest invoice
            preg_match("/{$prefix}(\d+){$suffix}/", $latestInvoice->invoice_no, $matches);
            $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;
        } else {
            // If no invoice found, start from 0
            $lastNumber = 0;
        }

        // Increment the number
        $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

        $number = stripslashes("{$prefix}{$newNumber}{$suffix}");

        // Generate the new invoice number
        return $number;
    }

    public function getGstAmountAttribute()
    {
        return ($this->total / 1.18) * 0.18;
    }

    public function getIsSameStateAttribute()
    {
        // TODO: Confirm with meet bhai, Accounts
        $companyGstin = substr(config('app.gstin'), 0, 2);

        $partyGstin = substr($this->client->account->gstin, 0, 2);

        if ($companyGstin != $partyGstin) {
            return false;
        }

        return true;
    }

    public function getCgstAttribute()
    {
        if ($this->is_same_state) {
            return ($this->total * 9) / 118;
        }

        return 0;
    }

    public function getSgstAttribute()
    {
        if ($this->is_same_state) {
            return ($this->total * 9) / 118;
        }

        return 0;
    }

    public function getIgstAttribute()
    {
        if (! $this->is_same_state) {
            return ($this->total * 18) / 118;
        }

        return 0;
    }

    public function getNetTotalAttribute()
    {
        return $this->total + $this->gst_amount;
    }

    public function getTypeAttribute()
    {
        $invoiceType = $this->invoice_no;

        if (strpos($invoiceType, 'SI') === 0) {
            return 'TAX';
        }

        return 'PROFORMA';
    }
}
