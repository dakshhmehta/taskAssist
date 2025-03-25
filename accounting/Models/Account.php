<?php

namespace Ri\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Romininteractive\Transaction\Traits\HasTransactions;

class Account extends Model
{
    use HasFactory, HasTransactions, SoftDeletes;

    protected $guarded = [];
}
