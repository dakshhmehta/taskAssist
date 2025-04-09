<?php

namespace Romininteractive\Transaction\Traits;

use App\Models\Material;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Romininteractive\Transaction\Transaction;

trait HasTransactions
{
    /**
     * Boot the soft deleting trait for a model.
     *
     * @return void
     */
    public static function bootHasTransactions() {}

    public function transactions($type = null)
    {
        $query = $this->morphToMany(Transaction::class, 'related', 'transaction__transaction_references');

        if($type == null){
            return $query;
        }

        if (is_array($type)) {
            // $query->whereIn('type', $type);
            $query->where(function ($q) use ($type) {
                foreach ($type as $t) {
                    if ($t === null) {
                        $q->orWhereNull('type');
                    } else {
                        $q->orWhere('type', $t);
                    }
                }
            });
        } else {
            $query->where('type', $type);
        }

        return $query;
    }

    public function credit($amount, Carbon $date = null, $description = null)
    {
        if (!$date) {
            $date = Carbon::now();
        }

        $transaction = $this->transactions()->create([
            'date'   => $date,
            'amount' => abs($amount),
            'description' => $description,
        ]);

        $transaction->associate($this);

        return $transaction;
    }

    public function debit($amount, Carbon $date = null, $description = null)
    {
        if (!$date) {
            $date = Carbon::now();
        }

        $transaction = $this->transactions()->create([
            'date'   => $date,
            'amount' => abs($amount) * -1,
            'description' => $description,
        ]);

        $transaction->associate($this);

        return $transaction;
    }


    public function balance($types = [])
    {
        $amount = $this->transactions($types)->sum('amount');

        return sprintf("%.2f", $amount);
    }

    public function delete()
    {
        // Delete the transaction associated with this expense
        $this->transactions()->delete();

        return parent::delete();
    }

    public function totalCredit()
    {
        return $this->transactions->where('amount', '<', 0)->sum('amount');
    }

    public function totalDebit()
    {
        return $this->transactions->where('amount', '>', 0)->sum('amount');
    }

    public function totalBalance()
    {
        // debit's are already stored as '-' sign so adding here not subtracting
        return ($this->totalCredit()) - $this->totalDebit();
    }

    // created scope for financial years from 1st april to 31st march
    public function scopeInCurrentFinancialYear($query)
    {
        $currentYear = Carbon::now()->year;

        if (Carbon::now()->month <= 3) {
            return $query->whereHas('transactions', function ($q) use ($currentYear) {
                $q->whereBetween('date', [
                    ($currentYear - 1) . '-04-01',
                    $currentYear . '-03-31',
                ]);
            });
        } else {
            return $query->whereHas('transactions', function ($q) use ($currentYear) {
                $q->whereBetween('date', [
                    $currentYear . '-04-01',
                    ($currentYear + 1) . '-03-31',
                ]);
            });
        }
    }

    // public function virtualStock($weight, $item)
    // {
    //     $virtualStockWeight = $weight * 0.20;

    //     $transaction = $this->transactions()->create([
    //         'date' => Carbon::now(),
    //         'amount' => $virtualStockWeight,
    //         'description' => 'Virtual Stock',
    //         'type' => 'virtual_stock'
    //     ]);
    //     $transaction->associate([
    //         Auth::user()->unit,
    //         $item
    //     ]);
    //     return $virtualStockWeight;
    // }

    public function scopeOfTypeNull($query)
    {
        return $query->whereHas('transactions', function ($q) {
            $q->whereNull('type');
        });
    }
}
