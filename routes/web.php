<?php

use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\WeeklyPlanController;
use App\Http\Controllers\WordPressDataController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::group(['middleware' => 'auth'], function(){
    Route::get('standup', [WeeklyPlanController::class, 'getStandupsheet']);
    Route::get('weekly-plan', [WeeklyPlanController::class, 'get']);
});

Route::get('wp-data', [WordPressDataController::class, 'getData']);

Route::get('invoices/{id}/print', [InvoicesController::class, 'getPrint'])->name('invoices.print');