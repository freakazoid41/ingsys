<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;

Route::get('/',                                             [AuthController::class, 'login'])->name('index');
Route::get('/seclogin',                                     [AuthController::class, 'login'])->name('login');

Route::get('/logout',                   [AuthController::class, 'logout'])->name('logout');

//test hook for permissions
/*Route::get('/panel/users', function (){
    return 'test';
})->where('any', '^((?!api).)*');*/


Route::redirect('/talklogin', '/seclogin', 301);
Route::redirect('/talkpanel', '/seclogin', 301);
Route::middleware(['auth:sanctum'])
    ->group(function () {
        
        Route::get('/facility',fn()       => view('frontapp'))->name('frontapp');
        Route::get('/facility/{any}',fn() => view('frontapp'))->where('any', '^((?!api).)*');

        Route::get('/kontent',       [AuthController::class,   'handlePanelRoute'])->name('talkapp');
        Route::get('/kontent/{any}', [AuthController::class,   'handlePanelRoute'])->where('any', '^((?!api).)*');


        Route::get('/export/{model}/{type?}',   [ExportController::class, 'index'])->name('.export-table');
        Route::get('/setfacility/{facility}',   [AuthController::class,   'setfacility']);
        Route::get('/closefacility',            [AuthController::class,   'closefacility']);


        Route::get('/live-logs/fetch', function () {
            $file = storage_path('logs/cron-'.date('Y-m-d').'.log');
            $lastPos = request('pos', 0);
            
            $lines = [];
            if (file_exists($file)) {
                clearstatcache();
                $size = filesize($file);
                
                if ($size > $lastPos) {
                    $handle = fopen($file, 'rb');
                    fseek($handle, $lastPos);
                    while (!feof($handle)) {
                        $line = fgets($handle);
                        if ($line) $lines[] = htmlspecialchars($line);
                    }
                    $lastPos = ftell($handle);
                    fclose($handle);
                }
            }

            return response()->json(['lines' => $lines, 'pos' => $lastPos]);
        });
});

Route::get('/order-file/{doc}', function ($doc){
    return decryptFile($doc,'view');
})->name('documentRoute');



