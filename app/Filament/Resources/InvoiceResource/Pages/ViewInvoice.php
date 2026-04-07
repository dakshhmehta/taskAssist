<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Parallax\FilamentComments\Actions\CommentsAction;
use Ri\Accounting\Models\Account;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Gate;

class ViewInvoice extends ViewRecord
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
                    } catch (\Exception $e) {
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
            // Actions\DeleteAction::make(),

            // For Tax Invoice, mark as paid should be actually making the accounting entry, and if its done, we consider it mark it as paid.
            Action::make('convert_tax_invoice')
                ->label('Convert to SI')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn(?Invoice $invoice): bool => Gate::allows('convertToTaxInvoice', $invoice))
                ->action(function (Invoice $invoice) {
                    $newInvoice = $invoice->createTaxInvoice();

                    return redirect(self::getUrl('edit', ['record' => $newInvoice]));
                }),

            CommentsAction::make(),
        ];
    }
}
