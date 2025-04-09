<?php

namespace Romininteractive\Transaction;

use App\Traits\CustomLogOptions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Ri\Accounting\Models\Account;
use Ri\Accounting\Models\JournalEntry;
use Romininteractive\Transaction\Collections\TransactionCollection;
use Romininteractive\Transaction\TransactionReference;
use Spatie\Activitylog\Traits\LogsActivity;

class Transaction extends Model
{
    use LogsActivity, CustomLogOptions;

    protected $table = 'transaction__transactions';

    protected $fillable = ['date', 'amount', 'description', 'type'];
    protected $dates = ['date'];

    public static $dontSaveAttributes = ['balance', 'balance_formatted'];

    protected $appends = ['account_id'];

    public function getDateFormattedAttribute($value)
    {
        return $this->date->format('d M, Y');
    }

    public function getDisplayAmountAttribute($value)
    {
        return currency($this->amount);
    }

    public static function add($amount, $description = null)
    {
        $transaction = new static;
        $transaction->date = Carbon::now();
        $transaction->amount = $amount;

        $transaction->description = $description;

        $transaction->save();

        return $transaction;
    }

    public function associate($model)
    {
        if ($model !== null) { // Check if $model is not null
            if (is_array($model)) {
                foreach ($model as &$m) {
                    $this->associate($m);
                }

                return $this;
            }
            $reference = TransactionReference::firstOrCreate([
                'transaction_id' => $this->id,
                'related_type' => get_class($model),
                'related_id' => $model->attributes[$model->getKeyName()],
            ]);
        }

        return $this;
    }

    public function dissociate($model)
    {
        $reference = TransactionReference::where([
            'transaction_id' => $this->id,
            'related_type' => get_class($model),
            'related_id' => $model->attributes[$model->getKeyName()],
        ])->first();

        if ($reference) {
            return $reference->delete();
        }

        return false;
    }

    public function references()
    {
        return $this->hasMany(TransactionReference::class);
    }

    public function delete()
    {
        $this->references()->delete();

        return parent::delete();
    }

    public function newCollection(array $models = [])
    {
        return new TransactionCollection($models);
    }

    public function save(array $options = [])
    {
        foreach (static::$dontSaveAttributes as &$att) {
            if (isset($this->attributes[$att])) {
                unset($this->attributes[$att]);
            }
        }

        return parent::save($options);
    }

    public function changeType($type)
    {
        $this->type = $type;
        $this->save();
    }

    public function getTypeFormattedAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->type));
    }

    public function closingBalance($model = null)
    {
        // $modelId = 0;
        // if($model){
        //     $modelId = $model->id.$model->updated_at;
        // }

        // $cacheKey = md5($this->id.$this->updated_at.$modelId);

        // if($amount = cache()->get($cacheKey, false)){
        //     return $amount;
        // }

        $previousClosing = static::where('date', '<', $this->date)
            ->relatedTo($model)
            ->sum('amount');

        $thisClosing = static::where('date', '=', $this->date)
            ->where('id', '<=', $this->id)
            ->relatedTo($model)->sum('amount');

        $amount = $previousClosing + $thisClosing;

        // cache()->put($cacheKey, $amount, now()->addYear());

        return $amount;
    }

    public function reference($entity)
    {
        // Split by the \ character, and if found 1, append the App\Models namespace to the string
        if (strpos('\\', $entity) == -1) {
            $entity = explode('\\', $entity);
            if (count($entity) == 1) {
                $entity = 'App\\Models\\' . $entity[0];
            }
        }

        $model = $this->references()->where('related_type', $entity)->first();

        return $model->related;
    }

    public function scopeRelatedTo($query, $reference)
    {
        $query->whereHas('references', function ($q) use ($reference) {
            if (is_object($reference)) {
                $q->where('related_type', get_class($reference));
                $q->where('related_id', $reference->id);
            } else {
                $q->where('related_type', $reference);
            }
        });
    }

    public function getAccountIdAttribute($value)
    {
        $account = $this->account;

        if ($account) {
            return $account->id;
        }

        return null;
    }

    public function getAccountAttribute()
    {
        $reference = $this->references()->first();

        if ($reference->related instanceof Account) {
            return $reference->related;
        }

        return null;
    }

    public function getCreditAttribute()
    {
        if ($this->amount > 0) return $this->amount;

        return null;
    }

    public function getDebitAttribute()
    {
        if ($this->amount < 0) return $this->amount;

        return null;
    }

    // public function getAccountNameByTransaction($transaction_id)
    // {
    //     $firstRecord = \DB::table('transaction__transaction_references')
    //         ->where('transaction_id', $transaction_id)
    //         ->first();

    //     // $cacheKey = md5($firstRecord->updated_at.$firstRecord->id);

    //     // if($label = cache()->get($cacheKey, false)){
    //     //     return $label;
    //     // }

    //     if ($firstRecord) {
    //         switch ($firstRecord->related_type) {
    //             case 'Modules\Accounting\Models\Account':
    //                 $account = Account::find($firstRecord->related_id);
    //                 $label = $account ? $account->account_name : null;
    //                 break;
    //             case 'App\Models\Vendor':
    //                 $vendor = Vendor::find($firstRecord->related_id);
    //                 $label = $vendor ? $vendor->print_name : null;
    //                 break;
    //             case 'App\Models\Customer':
    //                 $customer = Customer::find($firstRecord->related_id);
    //                 $label = $customer ? $customer->customer_name : null;
    //                 break;
    //             case 'App\Models\Unit':
    //                 $unit = Unit::find($firstRecord->related_id);
    //                 $label = $unit ? $unit->print_name : null;
    //                 break;
    //             default:
    //                 $label = null;
    //         }
    //     }

    //     // cache()->put($cacheKey, $label, now()->addYear());

    //     return $label;
    // }
    // public function getTypeAttribute()
    // {
    //     if ($this->relatedAccount instanceof SalesInvoice) {
    //         if ($this->relatedAccount->invoice_type == "Sales Invoice") {
    //             return 'DC';
    //         } else if ($this->relatedAccount->invoice_type == "Transfer") {
    //             return 'UTS';
    //         } else {
    //             return 'DC';
    //         }
    //     } elseif ($this->relatedAccount instanceof PurchaseInvoice) {
    //         if ($this->relatedAccount->purchase_type == "Direct Purchase" || $this->relatedAccount->purchase_type == "Unit Purchase") {
    //             return 'BP';
    //         } else if ($this->relatedAccount->purchase_type == "Transfer") {
    //             return 'UTP';
    //         } else {
    //             return 'BPU';
    //         }
    //         return 'BP';
    //     } elseif ($this->relatedAccount instanceof JournalEntry) {
    //         return $this->relatedAccount->voucher->code;
    //     } else {
    //         return 'Unit';
    //     }
    // }

    // public function getRefNoAttribute()
    // {
    //     if (!$this->relatedAccount) {
    //         return null; // Or you can return a default value or throw an exception, depending on your requirements.
    //     }

    //     if ($this->relatedAccount instanceof SalesInvoice) {
    //         if ($this->relatedAccount->invoice_number) {
    //             $refNo = explode('/', $this->relatedAccount->invoice_number)[1];
    //             return $refNo;
    //         } else {
    //             $refNo = explode('-', $this->relatedAccount->delivery_challan_number)[1];
    //             return $refNo;
    //         }
    //     } elseif ($this->relatedAccount instanceof PurchaseInvoice) {
    //         $refNo = explode('/', $this->relatedAccount->invoice_number)[1];
    //         return $refNo;
    //     } elseif ($this->relatedAccount instanceof JournalEntry) {
    //         $refNo = explode($this->relatedAccount->voucher->code, $this->relatedAccount->ref_id)[1];
    //         return $refNo;
    //     } else {
    //         return $this->relatedAccount->id;
    //     }
    // }

    public function getRelatedAccount(Account $thisAccount): Account
    {
        $reference = $this->references()
            ->where('related_type', '!=', get_class($thisAccount))
            ->first();

        $transaction = Transaction::relatedTo($reference->related)
            ->whereDoesntHave('references', function ($q) use ($thisAccount) {
                $q->where('related_type', get_class($thisAccount));
                $q->where('related_id', $thisAccount->id);
            })
            ->first();

        $reference = $transaction->references()
            ->where('related_type', '!=', $thisAccount)
            ->first();

        return $reference->related;
    }

    // public function getOtherReferences($transaction_id, $relatedAccount)
    // {
    //     $relatedAccountClass = get_class($relatedAccount);
    //     $records = \DB::table('transaction__transaction_references')
    //         ->where('transaction_id', $transaction_id)
    //         ->where('related_type', $relatedAccountClass)
    //         ->where('related_type', '!=', 'Romininteractive\Transaction\Transaction')
    //         ->get();

    //     $results = [];
    //     foreach ($records as $record) {
    //         switch ($record->related_type) {
    //             case 'Modules\Accounting\Models\Account':
    //                 $account = Account::find($record->related_id);
    //                 $results[] = $account ? $account->account_name : null;
    //                 break;
    //             case 'App\Models\Vendor':
    //                 $vendor = Vendor::find($record->related_id);
    //                 $results[] = $vendor ? $vendor->print_name : null;
    //                 break;
    //             case 'App\Models\Customer':
    //                 $customer = Customer::find($record->related_id);
    //                 $results[] = $customer ? $customer->customer_name : null;
    //                 break;
    //             case 'App\Models\Unit':
    //                 $unit = Unit::find($record->related_id);
    //                 $results[] = $unit ? $unit->print_name : null;
    //                 break;
    //             case 'App\Models\SalesInvoice':
    //                 $salesInvoice = SalesInvoice::find($record->related_id);
    //                 $results[] = $salesInvoice ? $salesInvoice->invoice_number : null;
    //                 break;
    //             case 'Modules\Accounting\Models\JournalEntry':
    //                 $journalENtry = JournalEntry::find($record->related_id);
    //                 $results[] = $journalENtry ? $journalENtry->ref_id : null;
    //                 break;
    //             default:
    //                 $results[] = null;
    //                 break;
    //         }
    //     }

    //     return $results;
    // }

    // public function relatedModel()
    // {
    //     $references = $this->references()->get();
    //     if ($references) {
    //         foreach ($references as $reference) {
    //             $relatedType = $reference->related_type;
    //             $relatedId = $reference->related_id;
    //             if (class_exists($relatedType)) {
    //                 $relatedAccount = $relatedType::find($relatedId);
    //                 if ($relatedAccount && $relatedType != 'App\Models\Material' && $relatedType != 'App\Models\Unit' && $relatedType != 'App\Models\Batch' && $relatedType != 'Romininteractive\Transaction\Transaction') {
    //                     return $relatedType::find($relatedId);
    //                 }
    //             }
    //         }
    //     }
    //     return null;
    // }
}
