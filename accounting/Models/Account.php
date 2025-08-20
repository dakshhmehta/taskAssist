<?php

namespace Ri\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ri\Accounting\Helper;
use Romininteractive\Transaction\Traits\HasTransactions;

class Account extends Model
{
    use HasFactory, HasTransactions, SoftDeletes;

    protected $guarded = [];

    public function getBalanceFormattedAttribute()
    {
        return Helper::accountBalance($this->balance());
    }

    public function getDropdownNameAttribute(){
        return $this->billing_name .' ('.$this->name.')'. ' (' . $this->balanceFormatted . ')';
    }
}
