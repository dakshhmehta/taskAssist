<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HostingResource\Pages;
use App\Filament\Resources\HostingResource\RelationManagers\ActivitylogRelationManager;
use App\Jobs\GenerateInvoice;
use App\Models\Hosting;
use App\Models\HostingPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class HostingResource extends Resource
{
    protected static ?string $model = Hosting::class;

    protected static ?string $navigationGroup = 'Domain & Hosting';

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $recordTitleAttribute = 'domain';

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Expiry' => $record->expiry_date->format(config('app.date_format')),
            'Server' => $record->server,
            'Web Space' => optional($record->package)->storage_formatted,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('domain')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('server')
                    ->maxLength(255),
                Forms\Components\Select::make('package_id')
                    ->label('Package')
                    ->required()
                    ->options(HostingPackage::all()->pluck('storage', 'id')),
                Forms\Components\DatePicker::make('expiry_date')
                    ->displayFormat('d-m-Y'),
                Forms\Components\Select::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'billing_name')
                    ->searchable()
                    ->nullable(),
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
                    ->searchable(),
                TextColumn::make('server')
                    ->searchable(),
                Tables\Columns\TextColumn::make('package.storage')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->dateTime('d-m-Y')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_invoiced')
                    ->boolean(),
                TextColumn::make('last_invoiced_date')
                    ->dateTime('d-m-Y'),
                IconColumn::make('is_suspended')
                    ->boolean(),
            ])
            ->defaultSort('expiry_date', 'ASC')
            ->filters([
                SelectFilter::make('ignored')
                    ->label('Ignore Status')
                    ->options([
                        'unignored' => 'Unignored Only',
                        'ignored' => 'Only Ignored',
                        'all' => 'Include Ignored',
                    ])
                    ->default('unignored')
                    ->query(function (Builder $query, $state) {
                        return match ($state) {
                            'ignored' => $query->whereNotNull('ignored_at'),
                            'all' => $query->includeIgnored(),
                            default => $query->excludeIgnored(),
                        };
                    }),
                Filter::make('expiry_date_range')
                    ->form([
                        Forms\Components\DatePicker::make('expiry_date_from')
                            ->label('Expiry From')
                            ->placeholder('Select Start Date'),
                        Forms\Components\DatePicker::make('expiry_date_to')
                            ->label('Expiry To')
                            ->placeholder('Select End Date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['expiry_date_from'], fn($q, $date) => $q->whereDate('expiry_date', '>=', $date))
                            ->when($data['expiry_date_to'], fn($q, $date) => $q->whereDate('expiry_date', '<=', $date));
                    })
                    ->label('Expiry Date Range'),
                TernaryFilter::make('owned_domain')
                    ->label('Is Hosting Only?')
                    ->trueLabel('Yes')
                    ->falseLabel('All')
                    ->queries(
                        true: fn(Builder $query) => $query->whereDoesntHave('domainLink'),
                        false: fn(Builder $query) => $query,
                        blank: fn(Builder $query) => $query,
                    ),
                TernaryFilter::make('suspended')
                    ->label('Suspended?')
                    ->placeholder('All')
                    ->trueLabel('Yes')
                    ->falseLabel('No')
                    ->default(false)
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('suspended_at'),
                        false: fn(Builder $query) => $query->whereNull('suspended_at'),
                        blank: fn(Builder $query) => $query,
                    ),
                SelectFilter::make('package_id')
                    ->label('Package')
                    ->options(HostingPackage::all()->pluck('storage', 'id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Action::make('visit')
                    ->label('Open URL')
                    ->url(fn(Hosting $hosting) => url('http://'.$hosting->domain)),
                Action::make('renew')
                    ->label('Renew')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn(Hosting $hosting) => $hosting->isRenewable())
                    ->action(fn(Hosting $hosting) => $hosting->renew()),

                Action::make('generateInvoice')
                    ->label('Generate Invoice')
                    ->visible(fn(Hosting $hosting) => $hosting->dueForRenewal())
                    ->color('success')
                    ->action(function (Hosting $hosting) {
                        GenerateInvoice::dispatch([$hosting], $hosting->expiry_date->subYear());
                    }),

                CommentsAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('generateInvoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Invoices for Selected Hostings')
                        ->modalDescription('This will create invoices for the selected hostings grouped by client.')
                        ->action(function ($records) {
                            // Group hostings by client_id
                            $groupedByClient = $records->groupBy('client_id');

                            $invoiceCount = 0;
                            foreach ($groupedByClient as $clientId => $hostings) {
                                if (!$clientId) {
                                    continue; // Skip hostings without a client
                                }

                                // Collect items for this client
                                $items = $hostings->all();

                                // Get the earliest expiry date
                                $earliestExpiryDate = $hostings->min('expiry_date');
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
            ActivitylogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHostings::route('/'),
            'create' => Pages\CreateHosting::route('/create'),
            'view' => Pages\ViewHosting::route('/{record}'),
            'edit' => Pages\EditHosting::route('/{record}/edit'),
        ];
    }
}
