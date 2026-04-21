<?php

use App\Http\Controllers\GptController;
use App\Http\Controllers\WordPressDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PersonalAssistantController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('ai')->group(function () {
    Route::get('/reseller-balance', [PersonalAssistantController::class, 'getResellerBalance']);

    Route::post('chat', [GptController::class, 'chat']);
});

Route::any('wp-data', [WordPressDataController::class, 'receiveData']);