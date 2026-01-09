<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Widgets\UserCLBalance;
use App\Filament\Resources\UserResource\Widgets\UserLeaveLedgerTable;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class UserLeaveLedger extends ViewRecord
{
    use InteractsWithRecord;

    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user-resource.pages.leave-ledger';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('adjust_balance')
                ->label('Adjust Leave Balance')
                ->form([
                    DatePicker::make('date')
                        ->required()
                        ->default(now())
                        ->maxDate(now()),
                    TextInput::make('amount')
                        ->label('Adjust Balance')
                        ->numeric()
                        ->required()
                        ->helperText('Enter negative value to deduct leaves (e.g., -1)'),
                    Textarea::make('description')
                        ->required()
                        ->label('Description'),
                ])
                ->action(function (array $data): void {
                    $amount = (float) $data['amount'];

                    $date = Carbon::parse($data['date']);
                    
                    if ($amount > 0) {
                        $txn = $this->record->credit($amount, $date, $data['description']);
                    } else {
                        $txn = $this->record->debit(abs($amount), $date, $data['description']);
                    }

                    $txn->changeType('cl');
                })
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name.' Leave Ledger';
    }

    public function getWidgets(): array
    {
        $data = ['user' => $this->record];

        return [
            UserCLBalance::make($data),
            UserLeaveLedgerTable::make($data),
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return 1;
    }
}
