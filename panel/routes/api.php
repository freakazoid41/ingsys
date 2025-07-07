<?php
use App\Http\Controllers\SystemController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PersonsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')
    ->namespace('\App\Http\Controllers\Api\V1')
    ->middleware(['auth:sanctum', 'verified'])
    ->group(function () {
        Route::get('/me', MeController::class);
    });*/
Route::post('/v1/auth/login/{type?}',             [AuthController::class, 'loginUser'])->name('login-user');
Route::middleware(['auth:sanctum'])->group(function () {
    Route::any('/v1/document/{id?}',                   [DocumentController::class, 'index']);
    Route::any('/v1/transaction/{id?}',                [DocumentController::class, 'transaction']);
    Route::post('/v1/table/{model}',                   [SystemController::class, 'table']);
    Route::any('/v1/persons/{id?}',                    [PersonsController::class, 'index']);
    Route::get('/v1/trans/prepare-payment',            [DocumentController::class, 'preparePayment']);
    Route::post('/v1/trans/set-payment',               [DocumentController::class, 'setPayment']);
    Route::post('/v1/trans/set-status',                [DocumentController::class, 'setStatus']);
    Route::any('/v1/dashboard/{type}/{period?}',       [ReportController::class, 'dashboard']);
    Route::any('/v1/setbackground',                    [PersonsController::class, 'changeBackground']);
    Route::any('/v1/checkunique',                      [DocumentController::class, 'checkUniqueLink']);
});   