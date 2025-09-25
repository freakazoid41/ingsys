<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FrontController extends Controller
{
    public function login(){
        
        //list all cards on here
        return view('front', [
            'scripts' => [
                //'/system/global/swal.js'
            ],
            'styles'  => [
                //'/system/front/pages/' .$page . '/page.css'
            ],
            //'pageScript' => '/system/front/pages/' . $page . '/page.js',
        ]);
    }
}
