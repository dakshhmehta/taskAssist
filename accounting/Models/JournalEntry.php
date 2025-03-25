<?php

namespace Ri\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ri\Accounting\Models\JournalEntryType;
use Romininteractive\Transaction\Traits\HasTransactions;

class JournalEntry extends Model
{
    use HasFactory, HasTransactions;

    protected $fillable = ['type_id', 'date', 'sr_no', 'remarks'];

    protected $casts = [
        'date' => 'date',
    ];

    public function type()
    {
        return $this->belongsTo(JournalEntryType::class, 'type_id');
    }

    public function getAmountAttribute()
    {
        return $this->totalBalance() / 2;
    }
}
