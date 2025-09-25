<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;


Route::get('/talklogin',                                    [AuthController::class, 'login'])->name('login');
Route::get('/ziyaretci/{facility}/{facilityid}',            [AuthController::class, 'frontLogin'])->name('frontLogin');
Route::post('/ziyaretcikontrol',                            [AuthController::class, 'loginFrontUser']);

Route::get('/logout',                   [AuthController::class, 'logout'])->name('logout');

//test hook for permissions
/*Route::get('/panel/users', function (){
    return 'test';
})->where('any', '^((?!api).)*');*/

Route::get('/facility',fn()      => view('frontapp'))->name('frontapp');
Route::get('/facility/{any}',fn() => view('frontapp'))->where('any', '^((?!api).)*');

Route::middleware(['auth:sanctum'])
    ->group(function () {
        

        Route::get('/talkpanel',fn() => view('talkapp'))->name('talkapp');
        Route::get('/talkpanel/{any}',fn() => view('talkapp'))->where('any', '^((?!api).)*');


        Route::get('/export/{model}/{type?}',    [ExportController::class, 'index'])->name('.export-table');
        Route::get('/setfacility/{facility}',  [AuthController::class,   'setfacility']);
        Route::get('/closefacility',            [AuthController::class,   'closefacility']);
});

Route::get('/order-file/{doc}', function ($doc){
    return decryptFile($doc,'view');
})->name('documentRoute');



