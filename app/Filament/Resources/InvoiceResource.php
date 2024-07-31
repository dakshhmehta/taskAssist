<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\Domain;
use App\Models\Hosting;
use App\Models\Timesheet;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                            ->unique(),
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
                                // Timesheet::class => 'Timesheet',
                            ])
                            ->required()
                            ->reactive(),
                        Forms\Components\Select::make('itemable_id')
                            ->label('Item')
                            ->options(function (callable $get) {
                                $type = $get('itemable_type');
                                if ($type === Domain::class) {
                                    return Domain::all()->pluck('tld', 'id');
                                }
                                if ($type === Hosting::class) {
                                    return Hosting::all()->pluck('domain', 'id');
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
                    ])
                    ->columns(3)
                    ->columnSpan(12)
                    ->addActionLabel('Add Invoice Item')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no'),
                Tables\Columns\TextColumn::make('date'),
                Tables\Columns\TextColumn::make('client.billing_name')->label('Client'),
                Tables\Columns\TextColumn::make('total')->label('Total'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('print')
                    // ->icon('printer')
                    ->label('Print')
                    ->url(fn(Invoice $invoice):string => route('invoices.print', [$invoice->id, 'force' => 1]), true)
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
