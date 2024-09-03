<?php

namespace App\Models;

use App\Traits\CustomLogOptions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Email extends Model
{
    use HasFactory;

    use LogsActivity, CustomLogOptions;

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];

    public function invoiceItems()
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
    }

    public function getDomainAccountsAttribute(){
        return $this->domain.' ('.$this->accounts_count.' accounts)';
    }

    public function invoices()
    {
        return $this->hasManyThrough(
            Invoice::class,
            InvoiceItem::class,
            'itemable_id', // Foreign key on InvoiceItem table
            'id', // Foreign key on Invoice table
            'id', // Local key on Domain table
            'invoice_id' // Local key on InvoiceItem table
        )->where('itemable_type', self::class);
    }

    public function getIsInvoicedAttribute()
    {
        if ($this->expiry_date->lte(Carbon::parse('2024-08-01'))) {
            return true;
        }

        $invoice = $this->invoices()->where('date', 'LIKE', $this->expiry_date->format('Y') . '%')->exists();

        return $invoice;
    }

    public function getLastInvoicedDateAttribute()
    {
        return optional($this->invoices()->orderBy('date', 'DESC')->first())->date;
    }
}
