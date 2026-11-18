<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;
use Ri\Accounting\Models\Account;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('accounting')
                ->label('Make Accounting Entry')
                ->color('info')
                ->form([
                    Select::make('revenue')
                        ->options(Account::where('type', 'revenue')->pluck('name', 'id'))
                        ->required()
                ])
                ->visible(fn(Invoice $invoice) => $invoice->type == 'TAX' && $invoice->client->account?->country == 'India')
                ->action(function (array $data, Invoice $invoice): void {
                    $revenueAccount = Account::findOrFail($data['revenue']);

                    try {
                        $invoice->createAccountingEntries($revenueAccount);

                        Notification::make()
                            ->title('Success')
                            ->body('Entry created successfully')
                            ->success()
                            ->send();
                    } catch(\Exception $e){
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('print')
                // ->icon('printer')
                ->label('Print')
                ->url(fn(Invoice $invoice): string => route('invoices.print', [$invoice->id, 'force' => 1]), true),
            Action::make('void')
                ->label('Void')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading(fn (Invoice $invoice): string => "Void invoice {$invoice->invoice_no}")
                ->modalDescription('Voiding cancels this invoice. It will no longer be counted as receivable, paid, converted or emailed.')
                ->form([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->rows(3)
                        ->required()
                        ->maxLength(1000),
                ])
                ->visible(fn(?Invoice $invoice): bool => Gate::allows('void', $invoice))
                ->action(function (array $data, Invoice $invoice): void {
                    $invoice->void($data['reason']);

                    Notification::make()
                        ->title("Invoice {$invoice->invoice_no} voided")
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
