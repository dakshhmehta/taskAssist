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

    // public function setOpeningBalance($type, $balance)
    // {
    //     // $openingBalance = $this->transactions()->where('type', 'opening_balance')->first();
    //     // if (!$openingBalance) {
    //     //     $openingBalance = $this->transactions()->create([
    //     //         'date'   => Carbon::now(),
    //     //         'amount' => $balance,
    //     //         'description' => 'Opening Balance',
    //     //         'type' => 'opening_balance',
    //     //     ]);
    //     // } else {
    //     //     $openingBalance->amount = $balance;
    //     //     $openingBalance->save();
    //     // }

    //     // return $openingBalance;
    //     $account = Account::firstOrCreate([
    //         'account_name' => 'Difference In Openings',
    //         'is_system' => true,
    //         'opening_balance' => 0.00,
    //         'account_type' => 'asset',
    //         'enable' => true,
    //     ]);
    //     $currentDate = Carbon::now();

    //     if ($currentDate->month < 4) {
    //         $currentDate->subYear();
    //     }

    //     $date = Carbon::create($currentDate->year, 4, 1, 0, 0, 0);
    //     if ($type === 'credit') {
    //         $transaction1 = $account->debit($balance, $date, 'Opening Balance');
    //         $transaction2 =  $this->credit($balance, $date, 'Opening Balance');
    //     } elseif ($type === 'debit') {
    //         $transaction1 = $account->credit($balance, $date, 'Opening Balance');
    //         $transaction2 =  $this->debit($balance, $date, 'Opening Balance');
    //     }
    //     $transaction1->associate([
    //         $transaction2
    //     ]);
    //     $transaction2->associate([
    //         $transaction1
    //     ]);
    // }


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
        return $this->transactions->where('amount', '>', 0)->sum('amount');
    }

    public function totalDebit()
    {
        return $this->transactions->where('amount', '<', 0)->sum('amount');
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

    // public function openingBalance($date = null, $isUnitCapital = false)
    // {
    //     if ($date === null) {
    //         return $this->transactions()->where('description', 'Opening Balance')->sum('amount');
    //     } else {
    //         $startDate = Carbon::parse($date);
    //         $currentYear = Carbon::now()->year;
    //         $financialYearStart = Carbon::create($currentYear, 4, 1);
    //         if ($startDate->lt($financialYearStart)) {
    //             $endDate = $financialYearStart;
    //         } else {
    //             $endDate = $startDate;
    //             $startDate = $financialYearStart;
    //         }
    //         if (Auth::user()->isAdmin() || $isUnitCapital) {
    //             return ($this->transactions()->whereDate('date', '>=', $startDate)->whereDate('date', '<', $endDate)->sum('amount'));
    //         } else {
    //             return ($this->transactions([null, 'unit'])->whereDate('date', '>=', $startDate)->whereDate('date', '<', $endDate)->sum('amount'));
    //         }
    //     }
    // }

    // public function stockPurchase($net_weight, $material, $batch, $invoice)
    // {
    //     $transaction = $this->transactions()->create([
    //         'date' => Carbon::now(),
    //         'amount' => $net_weight,
    //         'description' => $material->name . ' stock added with purchase #' . $invoice->id,
    //         'type' => 'stock'
    //     ]);
    //     $transaction->associate([
    //         Auth::user()->unit,
    //         $material,
    //         $batch,
    //         $invoice
    //     ]);
    //     return;
    // }

    // public function stockProduction($charcoalWeight, $batch, $production)
    // {
    //     $woodWeight = $charcoalWeight / 0.20;
    //     $material = Material::where('name', 'Wood')->first();
    //     $woodTransaction = $this->transactions()->create([
    //         'date' => now(),
    //         'amount' => -$woodWeight,
    //         'description' => 'Wood consumed for production in batch ' . $batch->name,
    //         'type' => 'stock'
    //     ]);
    //     $woodTransaction->associate([
    //         Auth::user()->unit,
    //         $material,
    //         $batch,
    //         $production
    //     ]);
    //     $charcoalMaterial = Material::where('name', 'CHARCOAL')->first();

    //     $charcoalTransaction = $this->transactions()->create([
    //         'date' => now(),
    //         'amount' => $charcoalWeight,
    //         'description' => 'Charcoal produced in batch ' . $batch->name,
    //         'type' => 'stock'
    //     ]);
    //     $charcoalTransaction->associate([
    //         Auth::user()->unit,
    //         $charcoalMaterial,
    //         $batch,
    //         $production
    //     ]);

    //     return;
    // }
}
