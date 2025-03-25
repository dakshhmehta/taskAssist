<?php

namespace Ri\Accounting\Filament\Accounting\Resources\JournalEntryResource\Pages;

use Carbon\Carbon;
use Ri\Accounting\Filament\Accounting\Resources\JournalEntryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Ri\Accounting\Models\Account;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Create the Journal Entry
        $record = static::getModel()::create($data);

        // Manually save transactions
        foreach ($data['transactions'] as $transaction) {
            $account = Account::find($transaction['account_id']);

            $amount = $transaction['amount'];
            if ($amount > 0) {
                $transaction = $account->credit($amount, Carbon::parse($data['date']), $data['remarks']);
                $transaction->associate([
                    $record
                ]);
            } else {
                $transaction = $account->debit($amount, Carbon::parse($data['date']), $data['remarks']);
                $transaction->associate([
                    $record
                ]);
            }
        }

        return $record;
    }
}
