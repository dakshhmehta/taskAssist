<?php

namespace Romininteractive\Transaction;

use Illuminate\Database\Eloquent\Model;

class TransactionReference extends Model
{
    protected $table             = 'transaction__transaction_references';
    protected $fillable          = ['transaction_id', 'related_type', 'related_id'];

    public function related()
    {
        return $this->morphTo();
    }
}
