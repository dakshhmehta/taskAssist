<?php

namespace Romininteractive\Transaction\Collections;

use Illuminate\Database\Eloquent\Collection;

class TransactionCollection extends Collection
{
    protected static $amount = 0;

    // public function __construct(array $models = [])
    // {
    //     static::$amount = 0;
    //     foreach (array_reverse($models, true) as &$item) {
    //         static::$amount += $item['amount'];
    //         $item['balance'] = static::$amount;
    //     }

    //     parent::__construct($models);
    // }
}
