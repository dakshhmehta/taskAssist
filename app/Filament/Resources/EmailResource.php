<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailResource\Pages;
use App\Jobs\GenerateInvoice;
use App\Models\Email;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static ?string $navigationGroup = 'Domain & Hosting';

    protected static ?string $navigationIcon = 'heroicon-o-at-symbol';

    protected static ?string $recordTitleAttribute = 'domain';

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Accounts' => $record->accounts_count,
            'Expiry' => $record->expiry_date->format(config('app.date_format')),
            'Ignored' => $record->isIgnored() ? 'Yes' : 'No',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),

                TextColumn::make('domain')
                    ->description(fn(Email $email) => $email->provider)
                    ->searchable(),
                TextColumn::make('accounts_count')
                    ->label('# of Accounts')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->dateTime('d-m-Y')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_invoiced')
                    ->boolean(),
                TextColumn::make('last_invoiced_date')
                    ->dateTime('d-m-Y'),
            ])
            ->defaultSort('expiry_date', 'DESC')
            ->filters([
                TernaryFilter::make('ignored')
                    ->label('Ignore')
                    ->trueLabel('Include Ignored')
                    ->falseLabel('Unignored Only')
                    ->default(false)
                    ->queries(
                        true: fn(Builder $query) => $query->includeIgnored(),
                        false: fn(Builder $query) => $query->excludeIgnored(),
                        blank: fn(Builder $query) => $query->excludeIgnored(),
                    ),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),

                CommentsAction::make(),

                Action::make('doIgnore')
                    ->label('Ignore')
                    ->icon('heroicon-o-x-circle')
                    ->action(fn(Email $email) => $email->ignore())
                    ->visible(fn(Email $email) => ! $email->isIgnored())
                    ->color('danger'),

                Action::make('doUnIgnore')
                    ->label('Unignore')
                    ->action(fn(Email $email) => $email->unIgnore())
                    ->visible(fn(Email $email) => $email->isIgnored())
                    ->color('warning'),

                // Action button for the Generate Invoice button
                Action::make('generateInvoice')
                    ->label('Generate Invoice')
                    // Previous invoice is older than 1 year and client already exists
                    ->visible(fn(Email $email) => $email->dueForRenewal())
                    ->color('success')
                    ->action(function (Email $email) {
                        GenerateInvoice::dispatch([$email], now());
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('generateInvoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Invoices for Selected Emails')
                        ->modalDescription('This will create invoices for the selected email accounts grouped by client.')
                        ->action(function ($records) {
                            // Group emails by client_id
                            $groupedByClient = $records->groupBy('client_id');

                            $invoiceCount = 0;
                            foreach ($groupedByClient as $clientId => $emails) {
                                if (!$clientId) {
                                    continue; // Skip emails without a client
                                }

                                // Collect items for this client (emails only)
                                $items = $emails->all();

                                $invoiceDate = now();

                                // Dispatch the job
                                GenerateInvoice::dispatch($items, $invoiceDate);
                                $invoiceCount++;
                            }

                            // Show success notification
                            \Filament\Notifications\Notification::make()
                                ->title('Invoices Generated')
                                ->body("Successfully queued {$invoiceCount} invoice(s) for generation.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmails::route('/'),
            'create' => Pages\CreateEmail::route('/create'),
            'edit' => Pages\EditEmail::route('/{record}/edit'),
        ];
    }
}
