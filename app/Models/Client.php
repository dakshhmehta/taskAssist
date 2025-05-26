<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Romininteractive\Transaction\Traits\IsLedger;

class Client extends Model
{
    use HasFactory, IsLedger, HasFilamentComments;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($client) {
            $client->receivable_amount = $client->getReceivable();
        });
    }

    public function getReceivableSlabAttribute()
    {
        return '< 10k';
    }

    public function accountNameColumn()
    {
        return $this->billing_name;
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function getDisplayNameAttribute()
    {
        return $this->billing_name . ' (' . $this->nickname . ')';
    }

    public function getReceivable()
    {
        $invoices = $this->invoices()->unpaid()->get();

        $total = 0;
        foreach ($invoices as &$invoice) {
            $total += $invoice->total;
        }

        return $total;
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function hostings()
    {
        return $this->hasMany(Hosting::class);
    }

    public function emails()
    {
        return $this->hasMany(Email::class);
    }
}
