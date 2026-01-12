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
Route::post('/v1/auth/login/{type?}',             [AuthController::class, 'loginUser'])->name('login-user')->middleware('throttle:5,1');
Route::middleware(['auth:sanctum'])->group(function () {
    Route::any('/v1/getcurrentf',                      [AuthController::class, 'getSession']);
    Route::any('/v1/content/{id?}',                    [DocumentController::class, 'index']);
    Route::any('/v1/transaction/{id?}',                [DocumentController::class, 'transaction']);
   
    Route::post('/v1/getfacility',                     [DocumentController::class, 'getFacility']);
    Route::post('/v1/getpersoninventory',              [DocumentController::class, 'getPersonInventories']);
    Route::post('/v1/table/{model}',                   [SystemController::class, 'table']);
    Route::any('/v1/persons/{id?}',                    [PersonsController::class, 'index']);
    Route::any('/v1/dashboard/{type}/{period?}',       [ReportController::class, 'dashboard']);
    Route::any('/v1/setbackground',                    [PersonsController::class, 'changeBackground']);
    Route::any('/v1/setnotificationstatus',            [SystemController::class, 'setNotificationStatus']);
    Route::any('/v1/setprocess/{qnid}',                [DocumentController::class, 'setProcess'])->middleware('throttle:5,1');
});   


Route::post('/set-locale', function (Request $request) {
    $locale = $request->all()['locale'];
    if (in_array($locale, ['en', 'tr'])) {  // Güvenlik için kontrol
        session(['locale' => $locale]);
        App::setLocale($locale);

        return response()->json(['success' => true,'session' => session('locale')]);
    }else{
        return response()->json(['error' => 'setleyemedi bişey var']);
    }
    return response()->json(['success' => true]);
});



