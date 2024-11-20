<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DomainResource\Pages;
use App\Filament\Resources\DomainResource\RelationManagers;
use App\Models\Domain;
use Filament\Forms;
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }
}
