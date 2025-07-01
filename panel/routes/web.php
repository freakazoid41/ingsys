<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;


Route::get('/',                   [AuthController::class, 'login'])->name('login');
Route::get('/logout',                  [AuthController::class, 'logout'])->name('logout');

//test hook for permissions
/*Route::get('/panel/users', function (){
    return 'test';
})->where('any', '^((?!api).)*');*/


Route::middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/panel', fn () => view('app'))->name('app');
        Route::get('/panel/users', function (){
            return strpos(auth('sanctum')->user()->name,'Admin') !== false ? view('app') : abort('403');
        })->where('any', '^((?!api).)*');
        Route::get('/panel/{any}', fn () => view('app'))->where('any', '^((?!api).)*');
        
        Route::get('/order-file/{doc}', function ($doc){
            return decryptFile($doc,'view');
        })->name('documentRoute');

        Route::post('/reportpdf/icmal',   [ExportController::class, 'reporticmal'])->name('.reportIcmal');
        Route::get('/export/{model}/{type?}',    [ExportController::class, 'index'])->name('.export-table');
});



