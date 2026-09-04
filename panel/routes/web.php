<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;

Route::get('/',                           [AuthController::class, 'tedariklogin'])->name('login');
Route::get('/tedarik',                    [AuthController::class, 'tedariklogin'])->name('tedarik-login');
Route::get('/module-select',              [AuthController::class, 'moduleSelect'])->name('module-select');
Route::get('/register',                   [AuthController::class, 'register'])->name('register');
//Route::get('/',                   [AuthController::class, 'login'])->name('login');
Route::get('/logout',                     [AuthController::class, 'logout'])->name('logout');
Route::get('/smscallback',                [AuthController::class, 'loginSms'])->name('login-sms');
Route::get('/auth/passwordreset/{code}' , [AuthController::class, 'passwordReset']);
Route::post('/auth/passchange' ,          [AuthController::class, 'passChange']);
//test hook for permissions
/*Route::get('/panel/users', function (){
    return 'test';
})->where('any', '^((?!api).)*');*/


Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckPermissionVersion::class])
    ->group(function () {
        /*Route::get('/panel', fn () => view('app'))->name('app');
        Route::get('/panel/users', function (){
            return strpos(auth('sanctum')->user()->name,'Admin') !== false ? view('app') : abort('403');
        })->where('any', '^((?!api).)*');
        Route::get('/panel/{any}', fn () => view('app'))->where('any', '^((?!api).)*');*/

        

        $coalAuth = function (){
            if(session('type_key') !== null && session('2f_success') !== null){
                // Module guard: need per-041-01
                $user = auth()->user();
                if($user && !(new \App\Services\PermissionService())->has($user, 'per-041-01')){
                    // allow DEV_ADMIN via 'all' fallback inside has()
                    if(!app(\App\Services\PermissionService::class)->has($user, 'all')){
                        abort(403, 'Bu modüle yetkiniz yok (Yönetim Paneli)');
                    }
                }
                return view('coalapp',['type' => session('type_key') == 'op-pert-admin' ? 'admin' : 'client']);
            }else{
                abort('403');
            }
        };

       

        Route::get('/coalpanel',$coalAuth)->name('app');
        Route::get('/coalpanel/{any}',$coalAuth)->where('any', '^((?!api).)*');

        $tedarikAuth = function (){
            if(session('type_key') !== null && session('2f_success') !== null){
                $user = auth()->user();
                if($user && !(new \App\Services\PermissionService())->has($user, 'per-041-02')){
                    if(!app(\App\Services\PermissionService::class)->has($user, 'all')){
                        abort(403, 'Bu modüle yetkiniz yok (Tedarik)');
                    }
                }
                return view('tedarikapp',['type' => session('type_key') == 'op-pert-admin' ? 'admin' : 'client']);
            }else{
                abort('403');
            }
        };
        Route::get('/tedarikpanel',$tedarikAuth)->name('tedarik-app');
        Route::get('/tedarikpanel/{any}',$tedarikAuth)->where('any', '^((?!api).)*');

        /*Route::get('/client',$auth)->name('app');
        Route::get('/client/{any}',$auth)->where('any', '^((?!api).)*');*/

        Route::get('/order-file/{doc}', function ($doc){
            return decryptFile($doc,'view');
        })->name('documentRoute');

        Route::post('/export/offer',             [ExportController::class, 'offerPdf'])->name('.offerPdf');
        Route::get('/export/{model}/{type?}',    [ExportController::class, 'index'])->name('.export-table');
        Route::get('/setapartment/{apartment}',  [AuthController::class,   'setapartment']);
        Route::get('/closeapartment',            [AuthController::class,   'closeapartment']);
});



