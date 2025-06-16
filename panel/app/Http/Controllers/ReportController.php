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
        return response()->json((new ReportServiceProvider())->dashboardInfo($type,$period));
	}
}