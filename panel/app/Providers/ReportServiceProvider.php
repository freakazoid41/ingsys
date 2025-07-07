<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sys_options;
use App\Models\Documents;

use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Transactions;
use Illuminate\Support\Facades\DB;

class ReportServiceProvider extends ServiceProvider
{
    public function __construct() {
       
    }

    public function dashboardInfo($type,$period = null){
        $data = [
            'success' => true
        ];
        return $data;
    }
}
