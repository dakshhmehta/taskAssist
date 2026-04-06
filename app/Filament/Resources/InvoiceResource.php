<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
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
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

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
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                // Auto-populate expiry_date from itemable when item is selected
                                if ($state) {
                                    $type = $get('itemable_type');
                                    $itemable = null;

                                    if ($type === Domain::class) {
                                        $itemable = Domain::find($state);
                                    } elseif ($type === Hosting::class) {
                                        $itemable = Hosting::find($state);
                                    } elseif ($type === Email::class) {
                                        $itemable = Email::find($state);
                                    }

                                    if ($itemable && isset($itemable->expiry_date)) {
                                        $set('expiry_date', $itemable->expiry_date);
                                    }
                                }
                            }),
                        // TODO: Auto fill based on domain extension from the config/pricing file
                        // OR in case hosting package, take it from Hosting Package pricing from the database
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount')
                            ->numeric(),

                        // TODO:  In case of the domain, take the expiry date from domain model
                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->nullable(),
                        Forms\Components\RichEditor::make('line_description')
                            ->label('Description'),
                    ])
                    ->columns(4)
                    ->columnSpan(12)
                    ->addActionLabel('Add Invoice Item'),

                // TODO: Default, 0 rows should be there
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
                    ->addActionLabel('Add Extra'),
                Textarea::make('footnote')
                    ->label('Footnote')
                    ->rows(3)
                    ->columnSpan(12)
                    ->placeholder('Leave empty for default: Next domain and hosting renewal: [Month], [Year+1]'),
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
                // TODO: Reuse these same filters in Domain -> Invoices tab
                Filter::make('paid_date_range')
                    ->form([
                        Forms\Components\DatePicker::make('paid_date_from')
                            ->label('Paid Date - From')
                            ->placeholder('Paid Date - From'),
                        Forms\Components\DatePicker::make('paid_date_to')
                            ->label('Paid Date - To')
                            ->placeholder('Paid Date - To'),

                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From')
                            ->placeholder('Created - From'),
                        Forms\Components\DatePicker::make('created_to')
                            ->label('Created - To')
                            ->placeholder('Created - To'),

                        // Dropdown with Yes / No field for Converted to Tax Invoice
                        Forms\Components\Select::make('converted_to_tax_invoice')
                            ->label('Converted to Tax Invoice')
                            ->options([
                                'yes' => 'Yes',
                                'no' => 'No',
                            ])
                            ->placeholder('Converted to Tax Invoice'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['paid_date_from'], function (Builder $query, $date) {
                                return $query->whereDate('paid_date', '>=', $date);
                            })
                            ->when($data['paid_date_to'], function (Builder $query, $date) {
                                return $query->whereDate('paid_date', '<=', $date);
                            })
                            ->when($data['created_from'], function (Builder $query, $date) {
                                return $query->whereDate('date', '>=', $date);
                            })
                            ->when($data['created_to'], function (Builder $query, $date) {
                                return $query->whereDate('date', '<=', $date);
                            })
                            ->when($data['converted_to_tax_invoice'], function (Builder $query, $value) {
                                if($value == 'no'){
                                    return $query->whereDoesntHave('taxInvoice');
                                }
                                if($value == 'yes'){
                                    return $query->whereHas('taxInvoice');
                                }
                            });
                    })
                    ->label('Paid Date Range'),
            ])
            ->actions([
                // For Tax Invoice, mark as paid should be actually making the accounting entry, and if its done, we consider it mark it as paid.
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
                ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => ! $record->hasTaxInvoice()),
                Action::make('print')
                    // ->icon('printer')
                    ->label('Print')
                    ->url(fn(Invoice $invoice): string => route('invoices.print', [$invoice->id, 'force' => 1]), true),
                
                CommentsAction::make(),
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
            'view' => ViewInvoice::route('/{record}/view'),
        ];
    }
}
