<?php

namespace App\Filament\Resources\DomainInvoicesRelationManagerResource\RelationManagers;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DomainResourceRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('invoices')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_no')
            ->columns([
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
                Tables\Columns\TextColumn::make('paid_date')
                    ->dateTime('d-m-Y')
                    ->label('Paid On'),
            ])
            ->defaultSort('date', 'DESC')

            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                ViewAction::make()
                    ->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record])),
                    Action::make('print')
                    // ->icon('printer')
                    ->label('Print')
                    ->url(fn(Invoice $invoice): string => route('invoices.print', [$invoice->id, 'force' => 1]), true),

                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
