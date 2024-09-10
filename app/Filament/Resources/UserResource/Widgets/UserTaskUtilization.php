<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UserTaskUtilization extends BaseWidget
{
    public $user;
    public $filterData;

    public function table(Table $table): Table
    {
        return $table    
            ->query(fn() => $this->fetchData())   
            ->columns([
                TextColumn::make('estimate'),
            ]);
    }

    public function fetchData()
    {
        // Fetch data from the database or any other source
        $estimates = config('options.estimate');

        $data = [];

        foreach($estimates as $estimate){
            $data['estimate'] = $estimate;
        }

        return collect($data);
    }
}
