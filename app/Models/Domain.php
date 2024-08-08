<?php

namespace App\Models;

use App\ResellerClub;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];

    public function sync()
    {
        $rc = ResellerClub::fetch($this->tld);

        $this->expiry_date = date('Y-m-d H:i:s', $rc[1]['orders.endtime']);
        $this->save();
    }

    public function invoiceItems()
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
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

    public function getIsInvoicedAttribute(){
        if($this->expiry_date->lte(Carbon::parse('2024-08-01'))){
            return true;
        }

        $invoice = $this->invoices()->where('date', 'LIKE', $this->expiry_date->format('Y').'%')->exists();

        return $invoice;
    }
}
