<?php

namespace App\Http\Controllers;

use App\Models\Persons;
use App\Models\Sys_options;
use App\Models\UserLog;
use App\Models\User;
use App\Rules\Recaptcha;
use App\Services\MailService;
use App\Providers\PersonsServiceProvider;

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

    public function coallogin(){
        
        //list all cards on here
        return view('auth.'. __FUNCTION__, [
            'scripts' => [
                //'/system/global/swal.js'
            ],
            'styles'  => [
                //'/system/front/pages/' .$page . '/page.css'
            ],
            'pageScript' => '/front/pages/' . __FUNCTION__ . '/page.js',
        ]);
    }
   

    public function loginSms(){
        //list all cards on here
        return view('auth.'. __FUNCTION__, [
            'scripts' => [
                
            ],
            'styles'  => [
            ],
            'pageScript' => '/front/pages/' . __FUNCTION__ . '/page.js',
        ]);
    }

    public function register(){
        
        //list all cards on here
        return view('auth.'. __FUNCTION__, [
            'scripts' => [
                //'/system/global/swal.js'
            ],
            'styles'  => [
                '/front/pages/' . __FUNCTION__ . '/page.css'
            ],
            'pageScript' => '/front/pages/' . __FUNCTION__ . '/page.js',
        ]);
    }

    public function registerUser(Request $request){
        try {
            $req = $request->all();
            $request->session()->flush();
            //validate request sended parameters
            $validateUser = Validator::make($req,[
                'email'    => 'required',
                'phone'    => 'required',
                'password' => 'required',
                'g-recaptcha-response' => ['required', new Recaptcha()],
            ], [
                'g-recaptcha-response.required' => 'reCAPTCHA zorunludur.',
            ]);

            if($validateUser->fails()) return redirect()->route('register')->with('register-error', 'Gerekli Bilgileri Doldurunuz...');

            //here  email must be unique in users table 
            if(Auth::attempt(['email' => $request->email,'password' => $request->password])){
                return redirect()->route('register')->with('register-error', 'Bu E-Posta kullanılmaktadır. Lütfen farklı Bir E-Posta ile kayıt olunuz...');
            }



            $res = (new PersonsServiceProvider())->setPerson(0,[
                'main_name'      => $req['email'] ?? '-',
                'user_status'    => '-1',
                'user_username'  => $req['email'] ?? '-',
                'user_password'  => $req['password'],
                'type_key'       => 'op-pert-reseller',
                'contphone**userfacilitygroup**main-0' => $req['phone'] ?? 0,

            ],$request->files->all(),'persons');


            if($res['success']){

                //send mail to admins for new registration
                (new PersonsServiceProvider())->sendregisterMails($req['email'],$req['phone']);


                Session::flush();
                Session::put('auth-forgot', 'Kullanıcı bilgileriniz incelendikten sonra kayıt işleminiz tamamlanacak ve yeni şifrenizle giriş yapabileceksiniz.');
                
                //Session::put('firstlogin' , 'Yeni Şifrenizle Giriş Yapabilirsiniz.');
                //Session::flush();
                return redirect()->route('login');
            }else{
                return redirect()->route('register')->with('register-error', 'Kayıt işlemi sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz...');
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ],500);
        }
    }

    public function loginUser(Request $request){
        try {

            $request->session()->flush();
            //validate request sended parameters
            $validateUser = Validator::make($request->all(),[
                'email'    => 'required',
                'password' => 'required',
                'g-recaptcha-response' => ['required', new Recaptcha()],
            ], [
                'g-recaptcha-response.required' => 'reCAPTCHA zorunludur.',
            ]);

            if($validateUser->fails()){
                return redirect()->route('login','admin')->with('login-error', 'Gerekli Bilgileri Doldurunuz...');
                /*return response()->json([
                    'success' => false,
                    'message' => 'Form Validate Error',
                    'error'   => $validateUser->errors()
                ],401);*/
            }


            $user   = User::where(['email' => $request->email,'status' => '1'])->first();
            if(!$user) return redirect()->route('login','admin')->with('login-error', 'Bilgiler Hatalıdır...');
            
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
                session(['grp_code'  => 'here']);

            }else{
                return redirect()->route('login')->with('login-error', 'Bilgiler Hatalıdır...');
            }
            
            $sysCode = 4000;
            

            //$token = $user->createToken("API TOKEN")->plainTextToken;

            //return redirect()->route('login')->with('login-success', $token);
            //$firstLogin = isset(auth('sanctum')->user()->id) ? count(UserLog::where('user_id',auth('sanctum')->user()->id)->get()) > 0 : false;
                
            //return to sms code page after sending sms code
            $code = rand(100000,999999);
            $code = '111111';
            //create code file for later checking
            \Illuminate\Support\Facades\Storage::disk('local')->put($request->all()['_token'].'-'.$user->person_id.'-login'.'.txt', $code);
            session(['login_person' => $user->person_id.'-login']);
            session(['token' => $request->all()['_token']]);
            session(['login_type' => 'normal']);
            //for ADM Company
            /*$call = $GLOBALS['SYS_CODE'] == 'GDZ' ? (new PosGuv()) : (new MobDev());

            //now send sms requests
            $response = (array) $call->sendSms([
                'to'   => $person->phone ?? '0',
                'desc' => $code,
            ]);*/
            //for debug
            /*$call = new MailService();

            $response = (array) $call->sendSms([
                'to'   => $request->email ?? '-',
                'desc' => $code,
            ]);*/
            $response = ['success' => true];
            if(!$response['success']) return redirect()->route('login')->with('login-error', 'Mail Gönderiminde Hata Oluştu...');

            return redirect()->route('login-sms')->with('login-code', ($_SERVER['HTTP_HOST'] === 'localhost:8000' ? $code : ''));
            

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ],500);
        }
    }

    /**
     * this method will check sended sms code and return to login page with parameters
     */
    public function checkCode(Request $request){
        
        $token      = session('token') ?? 'fake';
        $type       = session('login_type') ?? 'ldap';
        $loginRoute = $type == 'ldap' ? 'login' : 'login';
        $fileKey    = $token.'-'.session('login_person') ?? 'fake';
        $sendedCode = [];
        foreach($request->all() as $k => $v){
            if(strpos($k,'code_') !== false){
                $sendedCode[intval(explode('_',$k)[1]) - 1] = trim(strip_tags($v));

                if($v == '*') return redirect()->route($loginRoute)->with('sms-fail','Sms Kodu Geçerliliğini Yitirmiştir.');
            }
        }
        //merge sms code
        $sendedCode = implode('',$sendedCode);


        $exists = \Illuminate\Support\Facades\Storage::disk('local')->exists($fileKey.'.txt');
        if($exists){
            $code = \Illuminate\Support\Facades\Storage::get($fileKey.'.txt');
            \Illuminate\Support\Facades\Storage::disk('local')->delete($fileKey.'.txt');

            if($type == 'ldap' && $code == 'ISLDAP' && $request->isMethod('get')) $code = '111111';

            if($code == $sendedCode){
                //auth user here
                $user   = User::where('person_id',explode('-',session('login_person'))[0])->first();
                //$person = Persons::where(['id' => $user->person_id ])->first();

                $person = (new PersonsServiceProvider())->getPerson(null,null,true,$user->person_id)['person'][0] ?? [];
                
                Auth::login($user);

                //set person type to session
                if(!empty($person) /*|| strpos($person->sys_code,($GLOBALS['SYS_CODE'] === 'ADM' ? '5000' : '4000')) === false*/){
                   
                    //set permissions
                    $perms = json_decode((json_decode($person->permissions ?? '[]',true)[0] ?? [])['Value'] ?? '[]', true);
                    foreach($perms as $per) { session(['sper-'.$per => true]);}
                    session(['perms' => $perms]);
                    
                    //session(['is_client' => $person->client_id != '0']);
                    session(['person_id' => $person->id]);
                    session(['email'     => $user->email]);
                    //session(['type_id'   => $person->type_id]);
                    //session(['spec_code' => implode(',',$codes)]);
                    session(['ptitle'    => $person->name.' '.$person->surname]);
                    

                    


                    //here set user is accepted kvkk or not
                    /*$kvkkLog = UserLog::where([
                        'user_id'  => $user->id,
                        'relation' => 'users',
                        'type_id'  => Sys_options::select('id')->where('op_key', 'log-kvkk-'.strtolower($GLOBALS['SYS_CODE']))->first()->id,
                    ])->first();

                    if(!empty($kvkkLog) || $type == 'ldap'){
                        session(['kvkk_accepted' => true]);
                    }*/
                }

                $firstLogin = isset(auth('sanctum')->user()->id) ? (count(UserLog::where('user_id',auth('sanctum')->user()->id)->get()) == 0) : false;

                //redirect to renewing transactions for password mail request
                if(\Illuminate\Support\Facades\Storage::disk('local')->exists($token.'-refreshmailsms.txt')){
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($token.'-refreshmailsms'.'.txt');
                    $firstLogin = true;
                }

                $sysCode = 4000;
                UserLog::create([
                    'user_id'     => $user->id,
                    'sys_code'    => $sysCode,
                    'relation'    => 'users',
                    'relation_id' => $user->id,
                    'type_id'     => Sys_options::select('id')->where('op_key', 'log-login')->first()->id,
                    'description' => json_encode(array(
                        'desc' => ($person->name ?? '-').' Kullanıcısı sisteme giriş yaptı',
                    ),JSON_UNESCAPED_UNICODE)
                ]);

                $token = $user->createToken("API TOKEN")->plainTextToken;

                //check is is first login (user needs to change its password then)
                if($firstLogin) return redirect()->route($loginRoute)->with('sms-firstlogin', $token);


                return redirect()->route($loginRoute)->with('sms-success', $token);

                
            }else{
                return redirect()->route($loginRoute)->with('sms-fail','Girilen Kod Yanlıştır.');
            }
        }
        return redirect()->route($loginRoute)->with('sms-fail','Girilen Kod Yanlıştır.');
    }

    public function sendMail(Request $request){
        $req = $request->all();
        //first find record for phone number
        $user = User::where('email', $req['mail'])->first();
        if(!empty($user)){
       
            $key = bin2hex(random_bytes(10));
            \Illuminate\Support\Facades\Storage::disk('local')->put($key.'-refreshmail'.'.txt', $user->email);
            

            //get main template
            $temp = file_get_contents(public_path('coaltheme/mail/template.falanml'), true);
            //now find status template from database
            $status = file_get_contents(public_path('coaltheme/mail/forgot-pass.falanml'), true);

            $temp = str_replace("{*template*}", $status, $temp);
            
            //now fill template
            
            //find order
            $person = Persons::where('id',$user->person_id)->first();

           

            //now fill the keys
            $temp = str_replace("{*prs-title*}", $person->title, $temp);
            //set domain
            $temp = str_replace("{*domain*}", 'https://'.$request->getHttpHost(), $temp);
            //$temp = str_replace("{*system*}", $GLOBALS['SYS_CODE'] == 'GDZ' ? 'gdz' : 'adm', $temp);
            //set link
            $temp = str_replace("{*pass-url*}", $request->getHttpHost().'/auth/passwordreset/'.$key, $temp);

            $mailService = new MailService();
            $mailResponse = $mailService->sendMail([
                'to' => $user->email,
                'subject' => 'Şifre Değiştirme',
                'html' => $temp,
            ]);

            if ($mailResponse['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Şifre Sıfırlama Maili Mail Adresinize İletilmiştir.',
                ],200);
            }

            return response()->json([
                'success' => false,
                'message' => $mailResponse['message'] ?? 'E-posta gönderilirken hata oluştu.',
                'data' => Config::get('mail'),
            ],200);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Girdiğiniz eposta adresi sistemde kayıtlı ise e-posta gönderilecektir. Lütfen e-postanızı kontrol edin.',
            ],200);
        }
    }

    public function passwordReset(Request $request,$code){
        if(isset(auth('sanctum')->user()->id)){
       

            $email = auth('sanctum')->user()->email;
            
            session(['auth-forgot'=>$email]);

           
            return view('auth.'. __FUNCTION__, [
                'scripts' => [
                    //'/system/global/swal.js'
                ],
                'styles'  => [
                    '/front/pages/' . __FUNCTION__ . '/page.css'
                ],
                'pageScript' => '/front/pages/' . __FUNCTION__ . '/page.js',
            ]);
        }else{
            Session::flush();
            $exists = \Illuminate\Support\Facades\Storage::disk('local')->exists($code.'-refreshmail'.'.txt');
            if($exists){
                //send sms here then redirect to code check screen
                $user   = User::where('email',\Illuminate\Support\Facades\Storage::get($code.'-refreshmail.txt'))->first();

                if(empty($user)) redirect()->route('login');

                $person = Persons::where(['id' => $user->person_id/* , 'sys_code' => ($GLOBALS['SYS_CODE'] == 'GDZ' ? '4000' : '5000')*/])->first();
                //remove file
                \Illuminate\Support\Facades\Storage::disk('local')->delete($code.'-refreshmail'.'.txt');

                //return to sms code page after sending sms code
                $smsCode = rand(100000,999999);
                
                //create code file for later checking
                \Illuminate\Support\Facades\Storage::disk('local')->put($code.'-'.$user->person_id.'-login'.'.txt', $smsCode);
                session(['login_person' => $user->person_id.'-login']);
                session(['token'        => $code]);
                session(['login_type'   => 'normal']);

                //create password forgot flag file for sms check transaction
                \Illuminate\Support\Facades\Storage::disk('local')->put($code.'-refreshmailsms'.'.txt', $user->email);
                //for sms Company
                $call = new MailService();
                $response = (array) $call->sendSms([
                    'to'   => $user->email ?? '-',
                    'desc' => $smsCode,
                ]);
                
                if(!$response['success']) return redirect()->route('login')->with('login-error', 'Sms Gönderiminde Hata Oluştu...');

                return redirect()->route('login-sms')->with('login-code', ($_SERVER['HTTP_HOST'] === 'localhost:8000' ? $smsCode : ''));
            }
            
            return redirect()->route('login');
        }
    }

    public function passChange(Request $request){
        if(session('auth-forgot') && isset(auth('sanctum')->user()->id)){
            Auth::logout();
            auth('web')->logout();

            $user = User::where('email', session('auth-forgot'))->first();
            $user->password = Hash::make($request->all()['password']);
            $user->save();
            Session::flush();
            Session::put('auth-forgot', 'Yeni Şifrenizle Giriş Yapabilirsiniz.');
            
            //Session::put('firstlogin' , 'Yeni Şifrenizle Giriş Yapabilirsiniz.');
            //Session::flush();
            return redirect()->route('login');
        }else{
            return redirect()->route('login');
        }
    }

    public function checkMail(Request $request){
        $user = User::where('email',$request->email)->first();
        return response()->json([
            'success' => empty($user),
        ],200);
    }

    //this method will set current apartment
    public function setapartment(Request $request,$apartment){
        $apt = Sys_options::where('op_key',$apartment)->first();
        session(['grp_code'   => $apartment]);
        session(['grp_title'  => $apt->title ?? 'Apartmant Mevcut Değil']);
        return redirect()->route('app');
    }

    //this method will close apartment
    public function closeapartment(Request $request){
        $apt = Sys_options::where('op_key',session('grp_code'))->first();
        $apt->status = 0;
        $apt->save();
        return redirect('/panel/apartments');
    }

    public function logout(Request $request){
        $request->session()->flush();

        return redirect()->route('login','admin');
    }

    public function getPermissions(Request $request){
        return response()->json([
            'success' => true,
            'permissions' => session('perms')
        ],200);
    }
}