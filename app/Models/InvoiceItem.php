<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_id', 'itemable_id', 'itemable_type', 'price', 'discount_value', 'proforma_invoice_id', 'line_description', 'expiry_date'];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function itemable()
    {
        return $this->morphTo();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
