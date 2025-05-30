<?php

namespace Ri\Accounting\Filament\Accounting\Resources;

use Ri\Accounting\Filament\Accounting\Resources\JournalEntryResource\Pages;
use Ri\Accounting\Filament\Accounting\Resources\JournalEntryResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Ri\Accounting\Helper;
use Ri\Accounting\Models\Account;
use Ri\Accounting\Models\JournalEntry;
use Ri\Accounting\Models\JournalEntryType;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type_id')
                    ->relationship(name: 'type', titleAttribute: 'label')
                    // ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        $typeId = $get('type_id');
                        $type = JournalEntryType::find($typeId);

                        $set('sr_no', $type->getNextSerialNo());
                    })
                    ->required(),
                TextInput::make('sr_no')
                    ->label('Serial #')
                    ->required()
                    ->unique(ignoreRecord: true),
                DatePicker::make('date'),

                Forms\Components\Repeater::make('transactions')
                    ->columnSpan(12)
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->label('Account')
                            ->options(Account::all()->pluck('dropdown_name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (\Filament\Forms\Set $set, callable $get, $state, $context) {
                                if ($context !== 'create') {
                                    return;
                                }

                                $transactions = $get('../../transactions') ?? []; // Go 2 levels up (outside repeater item)

                                $total = 0;
                                $i = 0;
                                // dd($transactions);
                                $change = null;
                                foreach ($transactions as $key => $transaction) {
                                    if ($transaction['amount'] == null && $i > 0) {
                                        $change = $key;
                                        break;
                                    }

                                    $total += floatval($transaction['amount'] ?? 0);
                                    $i++;
                                }

                                if ($change) {
                                    $set('../../transactions.' . $change . '.amount', $total * -1);
                                }
                            })
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Credit/Debit Amount')
                            ->helperText('Negetive amount for Cr, Positive amount for Dr')
                            ->numeric()
                            ->required(),
                    ])
                    // ->addable(function (Forms\Get $get) {
                    //     $transactions = $get('transactions') ?? [];

                    //     $last = null;
                    //     foreach ($transactions as &$transaction) {
                    //         $last = $transaction;
                    //     }

                    //     if ($last === null) {
                    //         return true;
                    //     }
                    //     if (empty($last['account_id'])) {
                    //         return false;
                    //     }
                    //     // Disable Add if last row is missing data
                    //     return !empty($last['account_id']) && isset($last['amount']);
                    // })
                    ->columns(2)
                    ->minItems(2),

                Textarea::make('remarks')
                    ->rows(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date('d-m-Y'),
                TextColumn::make('sr_no')
                    ->label('Serial No')
                    ->searchable(),
                TextColumn::make('remarks')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn($state) => Helper::indianNumberingFormat(abs($state))),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                DeleteAction::make(),
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
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
        ];
    }
}
