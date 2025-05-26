<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationGroup = 'Domain & Hosting';

    protected static ?string $navigationIcon = 'heroicon-o-document';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->columnSpan(4)
                            ->searchable()
                            ->relationship('client', 'billing_name')
                            ->required(),
                        Forms\Components\DatePicker::make('date')
                            ->columnSpan(4)
                            ->required(),
                        Forms\Components\TextInput::make('invoice_no')
                            ->required()
                            ->columnSpan(4)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(12),
                Forms\Components\Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Forms\Components\Select::make('itemable_type')
                            ->label('Type')
                            ->options([
                                Domain::class => 'Domain',
                                Hosting::class => 'Hosting',
                                Email::class => 'Emails',
                                // Timesheet::class => 'Timesheet',
                            ])
                            ->required()
                            ->reactive(),
                        Forms\Components\Select::make('itemable_id')
                            ->label('Item')
                            ->options(function (callable $get) {
                                $type = $get('itemable_type');
                                if ($type === Domain::class) {
                                    return Domain::excludeIgnored()->get()->pluck('tld', 'id');
                                }
                                if ($type === Hosting::class) {
                                    return Hosting::excludeIgnored()->get()->pluck('domain', 'id');
                                }
                                if ($type === Email::class) {
                                    return Email::all()->pluck('domain_accounts', 'id');
                                }
                                // if ($type === Timesheet::class) {
                                //     return Timesheet::all()->pluck('name', 'id');
                                // }
                                return [];
                            })
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount')
                            ->numeric(),
                    ])
                    ->columns(4)
                    ->columnSpan(12)
                    ->addActionLabel('Add Invoice Item'),

                Forms\Components\Repeater::make('extras')
                    ->relationship('extras')
                    ->schema([
                        TextInput::make('line_title')
                            ->required()
                            ->label('Title'),
                        RichEditor::make('line_description')
                            ->label('Description'),
                        TextInput::make('line_duration')
                            ->label('Duration'),
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount')
                            ->numeric(),
                    ])
                    ->columns(5)
                    ->columnSpan(12)
                    ->addActionLabel('Add Extra')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('invoice_no')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->sortable()
                    ->searchable()
                    ->dateTime('d-m-Y'),
                Tables\Columns\TextColumn::make('client.billing_name')->label('Client')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total'),
                TextColumn::make('paid_date')
                    ->dateTime('d-m-Y')
                    ->label('Paid On'),
            ])
            ->defaultSort('date', 'DESC')
            ->filters([
                Filter::make('paid_date_range')
                    ->form([
                        Forms\Components\DatePicker::make('paid_date_from')
                            ->label('Paid Date - From')
                            ->placeholder('Paid Date - From'),
                        Forms\Components\DatePicker::make('paid_date_to')
                            ->label('Paid Date - To')
                            ->placeholder('Paid Date - To'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['paid_date_from'], function (Builder $query, $date) {
                                return $query->whereDate('paid_date', '>=', $date);
                            })
                            ->when($data['paid_date_to'], function (Builder $query, $date) {
                                return $query->whereDate('paid_date', '<=', $date);
                            });
                    })
                    ->label('Paid Date Range'),
            ])
            ->actions([
                Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->color('success')
                    ->form([
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Date')
                                    ->default(now())
                                    ->required(),
                                Textarea::make('remarks')
                                    ->label('Remarks'),
                            ])
                    ])
                    ->visible(fn(?Invoice $invoice): bool => Gate::allows('markAsPaid', $invoice))
                    ->action(function (array $data, Invoice $record): void {
                        $record->markAsPaid($data['date'], $data['remarks']);
                    }),
                Action::make('convert_tax_invoice')
                    ->label('Convert to SI')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(?Invoice $invoice): bool => Gate::allows('convertToTaxInvoice', $invoice))
                    ->action(function (Invoice $invoice) {
                        $newInvoice = $invoice->createTaxInvoice();

                        return redirect(self::getUrl('edit', ['record' => $newInvoice]));
                    }),
                Tables\Actions\EditAction::make(),
                Action::make('print')
                    // ->icon('printer')
                    ->label('Print')
                    ->url(fn(Invoice $invoice): string => route('invoices.print', [$invoice->id, 'force' => 1]), true)
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
