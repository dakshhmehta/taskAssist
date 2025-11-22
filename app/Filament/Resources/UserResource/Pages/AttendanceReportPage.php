<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\UserCheckIn;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class AttendanceReportPage extends ViewRecord implements HasTable
{
    protected static string $resource = UserResource::class;

    use InteractsWithTable;

    protected static string $view = 'filament.resources.user-resource.pages.attendance-report-page';

    public function getHeading(): string | Htmlable
    {
        return $this->record->name."'s Attendance Report";
    }

    public function table(Table $table): Table
    {
        $record = $this->record;

        return $table
            ->heading($record->name."'s Attendance Report")
            ->query(
                fn() => UserCheckIn::select(
                    'user_checkins.id',
                    'user_checkins.user_id',
                    'user_checkins.punch_at as check_in',
                    \DB::raw('(SELECT b.punch_at 
                              FROM user_checkins b 
                              WHERE b.user_id = user_checkins.user_id 
                                AND b.type = "OUT" 
                                AND b.punch_at > user_checkins.punch_at 
                              ORDER BY b.punch_at ASC 
                              LIMIT 1) as check_out')
                )
                    ->where('type', 'IN')
                    ->where('user_id', $record->id)
                    ->orderBy('punch_at')
            )
            ->columns([
                TextColumn::make('check_in')->dateTime(),
                TextColumn::make('check_out')->dateTime(),
                TextColumn::make('duration')
                    ->getStateUsing(
                        fn($record) =>
                        $record->check_out
                            ? now()->parse($record->check_in)->diffAsCarbonInterval($record->check_out)->cascade()->forHumans()
                            : 'N/A'
                    ),
            ]);
    }
}
