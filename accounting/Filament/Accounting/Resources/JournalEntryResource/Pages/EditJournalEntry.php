<?php

namespace Ri\Accounting\Filament\Accounting\Resources\JournalEntryResource\Pages;

use Carbon\Carbon;
use Ri\Accounting\Filament\Accounting\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Ri\Accounting\Models\Account;

class EditJournalEntry extends EditRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected $transactions;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['transactions'] = $this->record->transactions->map(fn($transaction) => [
            'account_id' => $transaction->account_id,
            'amount' => $transaction->amount,
        ])->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        $record->transactions()->delete();

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
