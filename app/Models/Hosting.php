<?php

namespace App\Models;

use App\Traits\CustomLogOptions;
use App\Traits\IgnorableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Spatie\Activitylog\Traits\LogsActivity;

class Hosting extends Model
{
    use HasFactory;

    use LogsActivity, CustomLogOptions, IgnorableTrait;

    use HasFilamentComments;

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'datetime',
        'suspended_at' => 'datetime',

        'ssl_expiry_date' => 'datetime',
    ];

    public function domainLink()
    {
        return $this->hasOne(Domain::class, 'tld', 'domain');
    }

    public function invoiceItems()
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
    }

    public function package()
    {
        return $this->belongsTo(HostingPackage::class);
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

    public function getIsSuspendedAttribute($val)
    {
        return $this->suspended_at !== null;
    }

    public function scopeActive($q)
    {
        return $q->whereNull('suspended_at');
    }

    public function renew($years = 1)
    {
        if ($this->expiry_date == null) {
            $this->expiry_date = now()->addYears($years);
            $this->save();

            return true;
        }

        $this->expiry_date = $this->expiry_date->addYears($years);

        return $this->save();
    }

    public function isRenewable()
    {
        return ($this->expiry_date->subDays(15)->lte(now()->endOfDay()));
    }

    public function getLastInvoicedDateAttribute()
    {
        return optional($this->invoices()->orderBy('date', 'DESC')->first())->date;
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
