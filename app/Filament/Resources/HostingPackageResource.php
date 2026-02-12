<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HostingPackageResource\Pages;
use App\Models\HostingPackage;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HostingPackageResource extends Resource
{
    protected static ?string $model = HostingPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Masters';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Add Storage
                TextInput::make('storage')
                    ->label('Storage')
                    ->numeric()
                    ->required(),
                // Add Emails
                TextInput::make('emails')
                    ->label('Emails')
                    ->required(),
                // Add Price
                TextInput::make('price')
                    ->label('Price')
                    ->numeric()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),

                TextColumn::make('storage'),
                TextColumn::make('emails'),
                TextColumn::make('price'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListHostingPackages::route('/'),
            'create' => Pages\CreateHostingPackage::route('/create'),
            'edit' => Pages\EditHostingPackage::route('/{record}/edit'),
        ];
    }
}
