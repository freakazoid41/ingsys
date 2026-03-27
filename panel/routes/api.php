<?php
use App\Http\Controllers\SystemController;
use App\Http\Controllers\Api\V1\User\MeController;
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
Route::post('auth/checkcode',        [AuthController::class,'checkCode']);
Route::middleware(['throttle:4,1'])->group(function () {
    Route::post('auth/sendmail' ,        [AuthController::class, 'sendMail']);
});
Route::post('/v1/auth/login/{type?}',             [AuthController::class, 'loginUser'])->name('login-user')/*->middleware('throttle:2,1')*/;
Route::post('/v1/auth/register',                  [AuthController::class, 'registerUser'])->name('register-user')/*->middleware('throttle:2,1')*/;
Route::post('/v1/auth/resetusercradentals/{id}',  [PersonsController::class, 'resetUserCradentals'])->name('restart-user');
// expose authenticated user endpoint for tests and API clients
Route::middleware('auth')->get('/v1/me', MeController::class);
Route::middleware(['auth:sanctum'])->group(function () {
    Route::any('/v1/document/{id?}',                   [DocumentController::class, 'index']);
    Route::any('/v1/transaction/{id?}',                [DocumentController::class, 'transaction']);
    Route::get('/v1/get-apartments',                   [DocumentController::class, 'getAparments']);
    Route::post('/v1/set-apartments',                  [DocumentController::class, 'setAparments']);
    Route::post('/v1/table/{model}',                   [SystemController::class, 'table']);

    Route::any('/v1/users/{id?}',                      [PersonsController::class, 'uindex']);
    Route::any('/v1/persons/{id?}',                    [PersonsController::class, 'index']);
    Route::get('/v1/roles/templates',                  [PersonsController::class, 'rolesTemplate']);
    Route::post('/v1/roles/templates',                 [PersonsController::class, 'rolesTemplate']);
    Route::delete('/v1/roles/templates/{id}',          [PersonsController::class, 'rolesTemplate']);

    Route::get('/v1/trans/prepare-payment',            [DocumentController::class, 'preparePayment']);
    Route::post('/v1/trans/set-payment',               [DocumentController::class, 'setPayment']);
    Route::post('/v1/trans/set-status',                [DocumentController::class, 'setStatus']);
    Route::any('/v1/dashboard/{type}/{period?}',       [ReportController::class, 'dashboard']);
    Route::any('/v1/setbackground',                    [PersonsController::class, 'changeBackground']);
    Route::get('/v1/getpermissions',                   [AuthController::class, 'getPermissions']);

});   