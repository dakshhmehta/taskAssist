<?php
 
namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivities as PagesListActivities;

class ListActivities extends PagesListActivities
{
    protected static string $resource = UserResource::class;
}