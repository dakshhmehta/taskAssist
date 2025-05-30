<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserLeaveResource\Pages;
use App\Models\User;
use App\Models\UserLeave;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserLeaveResource extends Resource
{
    protected static ?string $model = UserLeave::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Team';

    protected static ?string $label = 'Leave';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'NEW')->count();
    }

    public static function form(Form $form): Form
    {
        $user = \Auth::user();

        $leaveTypes = config('leave_types');

        $leaveTypes['CL'] = 'CL (Available: ' . $user->balance('cl') . ')';

        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->options(User::all()->pluck('name', 'id'))
                    ->default($user->id)
                    ->hidden(fn(): bool => !$user->is_admin)
                    ->reactive()
                    ->required(),
                Forms\Components\DatePicker::make('from_date')
                    ->displayFormat(config('app.date_format'))
                    ->required(),
                Forms\Components\DatePicker::make('to_date')
                    ->displayFormat(config('app.date_format'))
                    ->required(),
                Select::make('code')
                    ->options(function (Get $get) {
                        $leaveTypes = config('leave_types');

                        $u = $get('user_id');

                        $user = User::find($u);

                        $leaveTypes['CL'] = 'CL (Available: ' . $user->balance('cl') . ')';

                        return $leaveTypes;
                    })
                    ->required(),
                Textarea::make('remarks')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = new UserLeave();

                if (! auth()->user()->is_admin) {
                    $query = $query->where('user_id', auth()->user()->id);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),

                TextColumn::make('status')
                    ->description(fn(UserLeave $leave) => $leave->admin_remarks)
                    ->color(function (UserLeave $leave) {
                        if ($leave->status == 'NEW') {
                            return 'primary';
                        } elseif ($leave->status == 'APPROVED') {
                            return 'success';
                        }

                        return 'danger';
                    }),

                Tables\Columns\TextColumn::make('user.name'),
                TextColumn::make('leave_days'),
                Tables\Columns\TextColumn::make('from_date')
                    ->label('From')
                    ->dateTime(config('app.date_format'))
                    ->description(fn(Model $record) => $record->remarks)
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_date')
                    ->label('To')
                    ->dateTime(config('app.date_format')),
                TextColumn::make('code'),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->options(User::all()->pluck('name', 'id')),
                SelectFilter::make('code')
                    ->options(config('leave_types')),
                SelectFilter::make('status')
                    ->options([
                        'NEW' => 'New',
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->requiresConfirmation()
                    ->action(fn(UserLeave $leave) => $leave->approve())
                    ->visible(fn(UserLeave $leave) => (Auth::user()->is_admin) && $leave->status == 'NEW')
                    ->color('success'),
                Action::make('reject')
                    ->label('Reject')
                    ->form([
                        Textarea::make('admin_remarks')
                            ->label('Remarks')
                            ->required(),
                    ])
                    ->action(function ($data, UserLeave $leave) {
                        $leave->reject($data['admin_remarks']);
                    })
                    ->visible(fn(UserLeave $leave) => (Auth::user()->is_admin) && $leave->status == 'NEW')
                    ->color('danger'),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('from_date', 'desc')
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
            'index' => Pages\ListUserLeaves::route('/'),
            'create' => Pages\CreateUserLeave::route('/create'),
            'edit' => Pages\EditUserLeave::route('/{record}/edit'),
        ];
    }
}
