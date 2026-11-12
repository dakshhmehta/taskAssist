<?php

namespace App\Models;

use App\Support\FinancialYear;
use App\Traits\TaxableInvoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Ri\Accounting\Models\Account;
use Romininteractive\Transaction\Traits\HasTransactions;

class Invoice extends Model
{
    use HasFactory, HasTransactions, TaxableInvoice;

    use HasFilamentComments;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_VOID = 'void';

    protected $guarded = [];

    protected $touches = ['client'];

    protected $attributes = [
        'status' => self::STATUS_OPEN, // That's just for test env, sqlite driver
    ];

    protected $casts = [
        'date' => 'date',
        'paid_date' => 'date',
        'voided_at' => 'datetime',
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
        return $q->whereNull('paid_date')->whereDoesntHave('taxInvoice')->notVoid();
    }

    public function scopeNotVoid($q)
    {
        return $q->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', self::STATUS_VOID);
        });
    }

    public function scopeVoided($q)
    {
        return $q->where('status', self::STATUS_VOID);
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /**
     * Cancel the invoice. A reason is mandatory because a voided invoice is a
     * cancelled document — the reason is the audit trail for why.
     */
    public function void(string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required to void an invoice.');
        }

        if ($this->isVoid()) {
            throw new \Exception("Invoice '{$this->invoice_no}' is already void.");
        }

        $this->status = self::STATUS_VOID;
        $this->void_reason = trim($reason);
        $this->voided_at = now();

        $this->save();
    }

    public function markAsPaid($date = null, $remarks = null)
    {
        if ($this->isVoid()) {
            throw new \Exception("Invoice '{$this->invoice_no}' is void and cannot be marked as paid.");
        }

        $this->paid_date = $date;
        $this->payment_remarks = $remarks;

        $this->save();
    }

    public function revertPaidStatus()
    {
        $this->paid_date = null;
        $this->payment_remarks = null;

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

    /**
     * Invoice series are dash-terminated (DH-, SI-, SR-, RE-, PRO-). Normalize
     * the prefix so callers passing "SR", "sr" or "SR " cannot fork a separate
     * "SR00001/2026" series next to "SR-00001/2026".
     */
    protected static function normalizePrefix(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));

        if ($prefix === '' || str_ends_with($prefix, '-') || str_ends_with($prefix, '/')) {
            return $prefix;
        }

        return $prefix . '-';
    }

    public static function nextInvoiceNumber($prefix = 'DH-', $date = null)
    {
        $prefix = self::normalizePrefix((string) $prefix);

        // Numbering follows the financial year (April - March), so dates from
        // January to March still belong to the previous year's series.
        $suffix = '/' . FinancialYear::startYear($date ?? now());

        // Highest sequence already used for this prefix in the financial year.
        $lastNumber = 0;

        self::where('invoice_no', 'LIKE', $prefix . '%' . $suffix)
            ->pluck('invoice_no')
            ->each(function ($invoiceNo) use ($prefix, $suffix, &$lastNumber) {
                $pattern = '/' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '/';

                if (preg_match($pattern, $invoiceNo, $matches)) {
                    $lastNumber = max($lastNumber, (int) $matches[1]);
                }
            });

        // Increment the number
        $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

        // Generate the new invoice number
        return "{$prefix}{$newNumber}{$suffix}";
    }

    public function getGstAmountAttribute()
    {
        return ($this->total / 1.18) * 0.18;
    }

    public function getIsSameStateAttribute()
    {
        // TODO: Confirm with meet bhai, Accounts
        $companyGstin = substr(config('app.gstin'), 0, 2);

        $partyGstin = substr($this->client->account?->gstin, 0, 2);

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
        return $this->total - $this->gst_amount;
    }

    public function getTypeAttribute()
    {
        $invoiceType = $this->invoice_no;

        if (strpos($invoiceType, 'SI') === 0) {
            return 'TAX';
        }

        return 'PROFORMA';
    }

    public function getLutNoAttribute()
    {
        return config('app.gstin_lut_no');
    }

    public function hasItemsUnbilled(){
        $items = $this->items;
    }
}
