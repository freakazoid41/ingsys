<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;

Route::get('/',                           [AuthController::class, 'coallogin'])->name('login');
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

Route::get('/facility',fn()      => view('frontapp'))->name('frontapp');
Route::get('/facility/{any}',fn() => view('frontapp'))->where('any', '^((?!api).)*');

Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckPermissionVersion::class])
    ->group(function () {
        

        

        $coalAuth = function (){
            if(session('type_key') !== null && session('2f_success') !== null){
                return view('coalapp',['type' => session('type_key') == 'op-pert-admin' ? 'admin' : 'client']);
                /*switch(session('type_key')){
                    case 'op-pert-admin':
                        return view('app');
                        break;
                    case 'op-pert-buyer':
                        //return redirect('/client'); // an alternative to "redirect()->to()"
                        return view('client');
                        break;
                    default:
                        abort('403');
                    break;
                }*/
            }else{
                abort('403');
            }
        };

       

        Route::get('/coalpanel',$coalAuth)->name('app');
        Route::get('/coalpanel/{any}',$coalAuth)->where('any', '^((?!api).)*');

        /*Route::get('/client',$auth)->name('app');
        Route::get('/client/{any}',$auth)->where('any', '^((?!api).)*');*/

        Route::get('/order-file/{doc}', function ($doc){
            return decryptFile($doc,'view');
        })->name('documentRoute');

        Route::post('/export/offer',             [ExportController::class, 'offerPdf'])->name('.offerPdf');
        Route::get('/export/{model}/{type?}',    [ExportController::class, 'index'])->name('.export-table');
        Route::get('/setfacility/{facility}',  [AuthController::class,   'setfacility']);
        Route::get('/closefacility',            [AuthController::class,   'closefacility']);
});

Route::get('/order-file/{doc}', function ($doc){
    return decryptFile($doc,'view');
})->name('documentRoute');



