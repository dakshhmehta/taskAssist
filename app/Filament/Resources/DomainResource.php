<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DomainInvoicesRelationManagerResource\RelationManagers\DomainResourceRelationManager;
use App\Filament\Resources\DomainResource\Pages;
use App\Jobs\GenerateInvoice;
use App\Models\Domain;
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

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $navigationGroup = 'Domain & Hosting';

    protected static ?string $navigationIcon = 'heroicon-o-at-symbol';

    protected static ?string $recordTitleAttribute = 'tld';

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
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
                Tables\Columns\TextColumn::make('tld')
                    ->label('TLD')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->searchable()
                    ->dateTime('d-m-Y')
                    ->sortable(),
                IconColumn::make('is_invoiced')
                    ->boolean(),
                TextColumn::make('last_invoiced_date')
                    ->dateTime('d-m-Y'),
            ])
            ->defaultSort('expiry_date', 'ASC')
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
                Action::make('sync')
                    ->label('Refresh')
                    ->action(fn(Domain $domain) => $domain->sync())
                    ->color('success'),

                CommentsAction::make(),

                Action::make('doIgnore')
                    ->label('Ignore')
                    ->icon('heroicon-o-x-circle')
                    ->action(fn(Domain $domain) => $domain->ignore())
                    ->visible(fn(Domain $domain) => !$domain->isIgnored())
                    ->color('danger'),

                Action::make('doUnIgnore')
                    ->label('Unignore')
                    ->action(fn(Domain $domain) => $domain->unIgnore())
                    ->visible(fn(Domain $domain) => $domain->isIgnored())
                    ->color('warning'),

                // Action button for the Generate Invoice button, if the domain is not invoiced and refresh row when clicked
                Action::make('generateInvoice')
                    ->label('Generate Invoice')
                    // Previous invoice is older than 1 year and client is already exist
                    ->visible(fn(Domain $domain) => $domain->last_invoiced_date?->diffInYears(now()) >= 1 && $domain->client)
                    ->color('success')
                    ->action(function (Domain $domain) {
                        GenerateInvoice::dispatch([$domain, $domain->hosting ?? null], $domain->expiry_date->subYear());
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
                        ->modalHeading('Generate Invoices for Selected Domains')
                        ->modalDescription('This will create invoices for the selected domains grouped by client. Each domain\'s hosting will be included if it exists.')
                        ->action(function ($records) {
                            // Group domains by client_id
                            $groupedByClient = $records->groupBy('client_id');

                            $invoiceCount = 0;
                            foreach ($groupedByClient as $clientId => $domains) {
                                if (!$clientId) {
                                    continue; // Skip domains without a client
                                }

                                // Collect items for this client (domains + their hosting)
                                $items = [];
                                foreach ($domains as $domain) {
                                    $items[] = $domain;
                                    if ($domain->hosting) {
                                        $items[] = $domain->hosting;
                                    }
                                }

                                // Get the earliest expiry date from the domains for this client
                                $earliestExpiryDate = $domains->min('expiry_date');
                                $invoiceDate = \Carbon\Carbon::parse($earliestExpiryDate)->subYear();

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
            DomainResourceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }
}
