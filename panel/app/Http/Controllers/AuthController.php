<?php

namespace App\Http\Controllers;

use App\Models\Persons;
use App\Models\Sys_options;
use App\Models\User_logs;
use App\Models\User;
use App\Models\Documents;

use App\Providers\PersonnelService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function passage(Request $request,$path = null){

        if($path == null) abort(404);


        $data = (new Documents())->tableList(['filter' => [
                [
                    'key'   => 'form-type',
                    'type'  => '=',
                    'value' => 'op-doc-link-form'
                ],[
                    'key'   => 'type',
                    'type'  => '=',
                    'value' => 'op-doc-link'
                ],[
                    'key'   => 'short_link',
                    'type'  => '=',
                    'value' => $path
                ]
            ]
        ])['data'];

        if(!empty($data)){
            
            //update count
            $route = Documents::where('id',$data[0]->id)->first();
            $route->click_count = intval($route->click_count)+1;
            $route->save();

            $data = json_decode($data[0]->main_attr,true);
            foreach($data as $row){
                $data[$row['Key']] = $row['Value'];
            }

            return redirect($data['long_link'], 301); 
        }else{
            abort(404);
        }
    }

    public function login(){
        
        //list all cards on here
        return view('login', [
            'scripts' => [
                //'/system/global/swal.js'
            ],
            'styles'  => [
                //'/system/front/pages/' .$page . '/page.css'
            ],
            //'pageScript' => '/system/front/pages/' . $page . '/page.js',
        ]);
    }
    //

    /*public function createUser(Request $request){
        try{    
            //validate request sended parameters
            $validateUser = Validator::make($request->all(),[
                'email'    => 'required|email|unique:users,email',
                'password' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'Form Validate Error',
                    'error'   => $validateUser->errors()
                ],401);
            }

            $user = Users::create([
                'name'     => isset($request->name) ? $request->name : '-',
                'email'    => $request->email,
                'password' => Hash::make($request->password)
            ]);


            return response()->json([
                'success' => true,
                'message' => 'User Created..',
                'token'   => $user->createToken("API TOKEN")->plainTextToken
            ],200);



        }catch(\Throwable $e){
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                
            ],500);
        }
    }*/

    public function loginUser(Request $request){
        try {

            $request->session()->flush();
            //validate request sended parameters
            $validateUser = Validator::make($request->all(),[
                'email'    => 'required',
                'password' => 'required'
            ]);

            if($validateUser->fails()){
                return redirect()->route('login','admin')->with('login-error', 'Gerekli Bilgileri Doldurunuz...');
                /*return response()->json([
                    'success' => false,
                    'message' => 'Form Validate Error',
                    'error'   => $validateUser->errors()
                ],401);*/
            }


            $user   = User::where('email',$request->email)->first();
            $person = Persons::where(['id' => $user->person_id ?? 0,/* 'sys_code' => ($GLOBALS['SYS_CODE'] == 'GDZ' ? '4000' : '5000')*/])->first();

           

            if(!Auth::attempt(['email' => $request->email,'password' => $request->password]) || empty($person)){
                return redirect()->route('login','admin')->with('login-error', 'Bilgiler Hatalıdır...');
                /*return response()->json([
                    'success' => false,
                    'message' => empty($user) ? 'Kullanıcı Bulunamadı..' : 'Şifrenizi Kontrol Edip Tekrar Giriş Yapınız..',
                    'error'   => $validateUser->errors()
                ],401);*/
            }
            

            //set person type to session
            if(!empty($person)){
                $personType = Sys_options::where('id',$person->type_id)->first();
                session(['type_key' => $personType->op_key]);
                //session(['is_client' => $person->client_id != '0']);
                session(['person_id' => $person->id]);
                session(['email'     => $request->email]);
                session(['ptitle'    => $person->name.' '.$person->surname]);

            }
            
            /*User_logs::create([
                'user_id'     => auth('sanctum')->user()->id,
                'sys_code'    => $GLOBALS['SYS_CODE'] == 'GDZ' ? '4000' : '5000',
                'relation'    => 'users',
                'relation_id' => auth('sanctum')->user()->id,
                'type_id'     => Sys_options::select('id')->where('op_key', 'log-login')->first()->id,
                'description' => json_encode(array(
                    'desc' => $person->name.' kullanıcısı sisteme giriş yaptı',
                ),JSON_UNESCAPED_UNICODE)
            ]);*/

            $token = $user->createToken("API TOKEN")->plainTextToken;

            return redirect()->route('login')->with('login-success', $token);

            

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ],500);
        }
    }

    public function passwordReset(Request $request,$code){
        $exists = \Illuminate\Support\Facades\Storage::disk('local')->exists($code.'-refreshmail'.'.txt');
        if($exists || isset(auth('sanctum')->user()->id)){
        //if(session('key') === $code || isset(auth('sanctum')->user()->id)){
            /*$email = isset(auth('sanctum')->user()->email) ? auth('sanctum')->user()->email : session('auth');
            
            Session::flush();*/

            $email = isset(auth('sanctum')->user()->email) ? 
                        auth('sanctum')->user()->email : 
                        \Illuminate\Support\Facades\Storage::get($code.'-refreshmail.txt');
            //remove outside operation
            \Illuminate\Support\Facades\Storage::disk('local')->delete($code.'-refreshmail'.'.txt');
            
            Session::flush();

            session(['auth-forgot'=>$email]);

            return view('pages.front.passwordReset', [
                'email'   => $email,
                'scripts' => [
                    '/system/global/swal.js'
                ],
                'styles'  => [
                    '/system/front/pages/' . __FUNCTION__ . '/page.css',
                ],
                'pageScript' => '/system/front/pages/' . __FUNCTION__ . '/page.js',
            ]);
        }else{
            return redirect()->route('home');
        }
    }

    public function passChange(Request $request){
        if(session('auth-forgot')){
            $user = User::where('email', session('auth-forgot'))->first();
            $user->password = Hash::make($request->all()['pass']);
            $user->save();

            Session::flush();
            
            return response()->json([
                'success' => true,
            ],200);
        }else{
            return response()->json([
                'success' => false,
            ],200);
        }
    }

    public function checkMail(Request $request){
        $user = User::where('email',$request->email)->first();
        return response()->json([
            'success' => empty($user),
        ],200);
    }

    public function logout(Request $request){
        $request->session()->flush();

        return redirect()->route('login','admin');
    }
}