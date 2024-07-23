<?php

use App\Http\Controllers\WeeklyPlanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::group(['middleware' => 'auth'], function(){
    Route::get('standup', [WeeklyPlanController::class, 'getStandupsheet']);
    Route::get('weekly-plan', [WeeklyPlanController::class, 'get']);
});