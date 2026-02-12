<?php

namespace App\Models;

use App\ResellerClub;
use App\Traits\CustomLogOptions;
use App\Traits\IgnorableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Spatie\Activitylog\Traits\LogsActivity;

class Domain extends Model
{
    use HasFactory;

    use LogsActivity, CustomLogOptions, IgnorableTrait;

    use HasFilamentComments;

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'datetime',
        'ignored_at' => 'datetime',
    ];

    public function sync()
    {
        $rc = ResellerClub::fetch($this->tld);

        $this->expiry_date = date('Y-m-d H:i:s', $rc[1]['orders.endtime']);

        $this->client_id = $this->getLastInvoice()?->client_id;

        $this->save();
    }

    public function hosting()
    {
        return $this->hasOne(Hosting::class, 'domain', 'tld');
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

    public function getIsInvoicedAttribute()
    {
        if ($this->expiry_date == null) {
            return null;
        }

        if ($this->expiry_date->lte(Carbon::parse('2024-08-01'))) {
            return true;
        }

        $invoice = $this->invoices()->where('date', 'LIKE', $this->expiry_date->format('Y') . '%')->exists();

        return $invoice;
    }

    public function getLastInvoicedDateAttribute()
    {
        return optional($this->getLastInvoice())->date;
    }

    public function getLastInvoice()
    {
        return $this->invoices()->orderBy('date', 'DESC')->first();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
