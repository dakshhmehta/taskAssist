<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HostingPackageResource\Pages;
use App\Filament\Resources\HostingPackageResource\RelationManagers;
use App\Models\HostingPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HostingPackageResource extends Resource
{
    protected static ?string $model = HostingPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
