<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Pages\AttendanceReportPage;
use App\Filament\Resources\UserResource\Pages\UserLeaveLedger;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'Team';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->revealable(),
                TextInput::make('salary')
                    ->required()
                    ->numeric(),
                Select::make('salary_type')
                    ->required()
                    ->options(config('options.salary_type')),
                TextInput::make('work_hours')
                    ->label('Working Hours / Day')
                    ->rules(['numeric', 'integer', 'gte:1'])
                    ->required(),
                TextInput::make('biometric_id')
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

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                // TextColumn::make('performance'),
                TextColumn::make('stars'),
                TextColumn::make('work_hours')
                    ->label('Working Hours'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->toggleable(true, true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('star')
                    ->label('Adjust Star')
                    ->color('info')
                    ->form([
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                TextInput::make('star')
                                    ->label('Rating')
                                    ->numeric(true)
                                    ->rules(['integer'])
                                    ->required(),
                                TextInput::make('remarks')
                                    ->label('Remarks')
                                    ->required(),
                            ])
                    ])
                    ->visible(Auth::user()->is_admin)
                    ->action(function (array $data, User $record): void {
                        $record->adjustStar($data['star'], $data['remarks']);
                    }),
                Tables\Actions\EditAction::make(),
                ViewAction::make('view'),
                Action::make('leave-ledger')
                    ->label('Leave Ledger')
                    ->color('info')
                    ->visible(fn($record) => $record->id == auth()->user()->id || auth()->user()->is_admin)
                    ->url(fn($record) => UserResource::getUrl('leave-ledger', ['record' => $record])),
                Action::make('attendance-report')
                    ->label('Attendance Report')
                    ->color('info')
                    ->visible(fn($record) => $record->id == auth()->user()->id || auth()->user()->is_admin)
                    ->url(fn($record) => UserResource::getUrl('attendance-report', ['record' => $record]))
                // Action::make('activities')->url(fn ($record) => UserResource::getUrl('activities', ['record' => $record]))
                //     ->color('info')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])->visible(Auth::user()->is_admin),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // TasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'view' => Pages\ViewUser::route('/{record}'),
            'leave-ledger' => UserLeaveLedger::route('/{record}/leave-ledger'),
            'attendance-report' => AttendanceReportPage::route('/{record}/attendance-report'),
        ];
    }
}
