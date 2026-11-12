<?php

namespace App\Http\Controllers;

use App\Providers\ReportServiceProvider;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Upload;
use Illuminate\Support\Facades\Validator;


class ReportController extends Controller
{
    public function dashboard(Request $request,$type,$period = null){
        // Dashboard aggregates run several wide COUNT queries; a short cache
        // keeps multi-widget dashboards from re-running them on every mount.
        $cacheKey = 'dash:'.$type.':'.($period ?? '-').':'.($GLOBALS['SYS_CODE'] ?? '').':'.session('type_key', '').':'.session('person_id', '');
        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($type, $period) {
            return (new ReportServiceProvider())->dashboardInfo($type, $period);
        });

        return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE);
	}
}