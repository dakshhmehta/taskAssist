<?php

namespace Romininteractive\Transaction\Traits;

use Illuminate\Database\Eloquent\Model;
use Ri\Accounting\Models\Account;

trait IsLedger
{
    public static function bootIsLedger()
    {
        static::saved(function (Model $model) {
            $account = $model->account()->first();

            if(! $account){
                $model->syncWithLedger();
            }
        });
    }

    public function syncWithLedger()
    {
        $account = $this->account()->first();

        if ($account) {
            $account->name = $this->accountNameColumn(); // Fallback if name doesn't exist
            $account->type = $this->ledgerType();
            $account->save();
        } else {
            $account = $this->account()->create([
                'name' => $this->accountNameColumn(),
                'type' => $this->ledgerType(),
            ]);

            $this->{$this->accountIdColumnName()} = $account->id;
            $this->save();
        }
    }

    public function accountNameColumn()
    {
        throw new \Exception('accountNameColumn() must be implemented in your model');
    }

    public function isLedger(): bool
    {
        return true;
    }

    public function ledgerType(): string
    {
        return 'Asset'; // You can override this in your model if needed
    }

    public function accountIdColumnName(): string
    {
        return 'account_id';
    }

    public function account()
    {
        return $this->hasOne(Account::class, 'id', $this->accountIdColumnName());
    }
}
