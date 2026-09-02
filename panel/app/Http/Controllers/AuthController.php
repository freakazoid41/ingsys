<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Persons;
use App\Models\Sys_options;
use App\Models\UserLog;
use App\Models\User;
use App\Rules\Recaptcha;
use App\Providers\EmailServiceProvider;
use App\Providers\PersonsServiceProvider;
use Illuminate\Support\Facades\File;
use App\Services\MailService;

use App\Services\SmsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Models\ActiveSession;
use Carbon\Carbon;

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

    public function tedariklogin(){
        return view('auth.tedariklogin', [
            'scripts' => [],
            'styles'  => [],
            'pageScript' => '/front/pages/tedariklogin/page.js',
        ]);
    }
   

    public function loginSms(){
        return view('auth.loginSms', [
            'scripts' => [],
            'styles'  => [],
            'pageScript' => '/front/pages/loginSms/page.js',
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
            if ($request->header('X-Requested-With') !== 'XMLHttpRequest') {
                $request->session()->flush();
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
                    'user_role'      => 'immutable-reseller',
                    'type_key'       => 'op-pert-reseller',
                    'contphone**userfacilitygroup**main-0' => $req['phone'] ?? 0,
                    'contmail**userfacilitygroup**main-0' => $req['email'] ?? 0,
                    'conttitle**userfacilitygroup**main-0' => 'İletişim Bilgisi',

                ],$request->files->all(),'persons');
                //if is approve later we will add company info to it
                
                
                if($res['success']){

                    //send mail to admins for new registration
                    (new EmailServiceProvider())->sendregisterMails($req['email'],$req['phone']);


                    Session::flush();
                    Session::put('auth-forgot', 'Kullanıcı bilgileriniz incelendikten sonra kayıt işleminiz tamamlanacak ve yeni şifrenizle giriş yapabileceksiniz.');
                    
                    //Session::put('firstlogin' , 'Yeni Şifrenizle Giriş Yapabilirsiniz.');
                    //Session::flush();
                    return redirect()->route('login');
                }else{
                    return redirect()->route('register')->with('register-error', 'Kayıt işlemi sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz...');
                }

            }else{

                $validateUser = Validator::make($req,[
                    'email'    => 'required',
                    'phone'    => 'required',
                    'password' => 'required',
                    'cli_id'   => 'required',
                ], []);

                if($validateUser->fails()) return response()->json([
                    'success' => false,
                    'message' => 'Gerekli bilgileri gönderiniz.',
                ],200);

                //here  email must be unique in users table 
                if(Auth::attempt(['email' => $req['email'],'password' => $req['password']])){
                    return response()->json([
                        'success' => false,
                        'message' => 'Bu Eposta Kullanılmaktadır.',
                    ],200);
                }

                $res = (new PersonsServiceProvider())->setPerson(0,[
                    'main_name'      => $req['email'] ?? '-',
                    'user_status'    => '-1',
                    'user_username'  => $req['email'] ?? '-',
                    'user_password'  => $req['password'],
                    'user_role'      => 'immutable-reseller',
                    'type_key'       => 'op-pert-reseller',
                    'contphone**userfacilitygroup**main-0'        => $req['phone'] ?? 0,
                    'contmail**userfacilitygroup**main-0'         => $req['email'] ?? 0,
                    'conttitle**userfacilitygroup**main-0'        => 'İletişim Bilgisi',
                    'cliid**userclientgroup**20260416060800-0'    => $req['cli_id'] ?? 0,
                    'clicode**userclientgroup**20260416060800-0'  => $req['cli_code'] ?? '',
                    'clititle**userclientgroup**20260416060800-0' => $req['cli_title'] ?? '',

                ],$request->files->all(),'persons');

                if($res['success']){

                    //send mail to admins for new registration
                    (new EmailServiceProvider())->sendregisterMails($req['email'],$req['phone']);

                    return response()->json([
                        'success' => true,
                        'message' => 'Kullanıcı bilgileriniz incelendikten sonra kayıt işleminiz tamamlanacak ve yeni şifrenizle giriş yapabileceksiniz.',
                    ],200);
                }else{
                    return response()->json([
                        'success' => false,
                        'message' => 'Kayıt işlemi sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz...',
                    ],200);
                }

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
            $origin = $request->input('auth_panel') ?? $request->route('type') ?? null;
            $isTedarik = ($origin === 'tedarik');
            // flush but preserve origin for 2FA flow
            $request->session()->flush();
            if ($isTedarik) session(['auth_panel' => 'tedarik']);
            else session(['auth_panel' => 'admin']);
            //validate request sended parameters
            $validateUser = Validator::make($request->all(),[
                'email'    => 'required',
                'password' => 'required',
                'g-recaptcha-response' => ['required', new Recaptcha()],
            ], [
                'g-recaptcha-response.required' => 'reCAPTCHA zorunludur.',
            ]);

            $failRoute = $isTedarik ? 'tedarik-login' : 'login';
            if($validateUser->fails()){
                return redirect()->route($failRoute)->with('login-error', 'Gerekli Bilgileri Doldurunuz...');
            }


            $user   = User::where(['email' => $request->email,'status' => '1'])->first();
            if(!$user) return redirect()->route($failRoute)->with('login-error', 'Bilgiler Hatalıdır...');

            // Lockout configuration
            $maxAttempts = 5;
            $lockMinutes = 15; // minutes
            $attemptsKey = 'login:attempts:'.strtolower($request->email);
            $lockKey = 'login:locked:'.strtolower($request->email);

            // If account is locked, block even correct password
            if(Cache::has($lockKey)){
                $lockedUntil = Cache::get($lockKey);
                $remaining = Carbon::parse($lockedUntil)->diffInMinutes(Carbon::now());
                return redirect()->route($failRoute)->with('login-error', 'Hesabınız geçici olarak kilitlendi. Lütfen '.($remaining > 0 ? $remaining : 1).' dakika sonra tekrar deneyiniz.');
            }

            $person = (new PersonsServiceProvider())->getPerson(null,null,true,$user->person_id)['person'][0] ?? [];
            
            //$person = Persons::where(['id' => $user->person_id ?? 0,/* 'sys_code' => ($GLOBALS['SYS_CODE'] == 'GDZ' ? '4000' : '5000')*/])->first();

           

            if(!Auth::attempt(['email' => $request->email,'password' => $request->password]) || empty($person)){
                UserLog::create([
                    'user_id'     => $user->id,
                    'sys_code'    => $GLOBALS['SYS_CODE'] ?? 0,
                    'relation'    => 'users',
                    'relation_id' => $user->id,
                    'type_id'     => Sys_options::where('op_key', 'log-login-failed')->value('id') ?? 0,
                    'description' => json_encode(array(
                        'desc' => 'Başarısız şifre denemesi',
                    ),JSON_UNESCAPED_UNICODE)
                ]);
                // increment attempts
                $attempts = Cache::get($attemptsKey, 0) + 1;
                Cache::put($attemptsKey, $attempts, now()->addMinutes($lockMinutes));

                if($attempts >= $maxAttempts){
                    $lockedUntil = Carbon::now()->addMinutes($lockMinutes)->toDateTimeString();
                    Cache::put($lockKey, $lockedUntil, $lockMinutes * 60);

                    // log lock event
                    try{
                        UserLog::create([
                            'user_id'     => $user->id ?? 0,
                            'sys_code'    => $GLOBALS['SYS_CODE'] ?? 0,
                            'relation'    => 'users',
                            'relation_id' => $user->id ?? 0,
                            'type_id'     => Sys_options::where('op_key', 'log-lock')->value('id') ?? 0,
                            'description' => json_encode(['desc' => 'Hesap kilitlendi (çoklu başarısız giriş).', 'email' => $request->email], JSON_UNESCAPED_UNICODE),
                        ]);
                    }catch(\Throwable $e){
                        // swallow logging errors to not break login flow
                    }

                    return redirect()->route($failRoute)->with('login-error', 'Çok sayıda başarısız giriş nedeniyle hesabınız '. $lockMinutes .' dakika kilitlendi.');
                }

                return redirect()->route($failRoute)->with('login-error', 'Bilgiler Hatalıdır... Kalan deneme hakkı: '.max(0, $maxAttempts - $attempts));
            }
            

            //successful login: clear attempts/lock
            Cache::forget($attemptsKey);
            Cache::forget($lockKey);

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
                return redirect()->route($failRoute)->with('login-error', 'Bilgiler Hatalıdır...');
            }
            
            
            

            $token = $request->input('_token');
            $sendResult = $this->generateAndSendTwoFactorCode($user, $person, $token);

            if (empty($sendResult['success'])) {
                return redirect()->route($failRoute)->with('login-error', 'SMS gönderiminde hata oluştu: ' . ($sendResult['message'] ?? ''));
            }

            return redirect()->route('login-sms')->with('login-code', ($_SERVER['HTTP_HOST'] === 'localhost:8000' ? ($sendResult['debug_code'] ?? '') : ''));
            

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
        $panelOrigin = session('auth_panel') ?? 'admin';
        $loginRoute = $panelOrigin === 'tedarik' ? 'tedarik-login' : 'login';
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
            $lastModified = File::lastModified( \Illuminate\Support\Facades\Storage::disk('local')->exists($fileKey.'.txt') ? \Illuminate\Support\Facades\Storage::disk('local')->path($fileKey.'.txt') : '');
           
            // 120 saniyeden eski kodları reddet
            if($lastModified !== null){
                $elapsedSeconds = Carbon::createFromTimestamp($lastModified)->diffInSeconds(Carbon::now());
                if($elapsedSeconds > 120){
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($fileKey.'.txt');
                    return redirect()->route($loginRoute)->with('sms-fail','SMS kodu süresi doldu. Lütfen tekrar isteyin.');
                }
            }

            $code = \Illuminate\Support\Facades\Storage::get($fileKey.'.txt');
            \Illuminate\Support\Facades\Storage::disk('local')->delete($fileKey.'.txt');

            //auth user here
            $user   = User::where('person_id',explode('-',session('login_person'))[0])->first();
            if($code == $sendedCode){
                
                //$person = Persons::where(['id' => $user->person_id ])->first();

                $person = (new PersonsServiceProvider())->getPerson(null,null,true,$user->person_id)['person'][0] ?? [];
                
                Auth::login($user);

                //set person type to session
                if(!empty($person) /*|| strpos($person->sys_code,($GLOBALS['SYS_CODE'] === 'ADM' ? '5000' : '4000')) === false*/){
                    $personType = Sys_options::where('id',$person->type_id)->first();
                        loadUserPermissionsToSession($user);
                    
                    //session(['is_client' => $person->client_id != '0']);
                    session(['person_id' => $person->qnid]);
                    session(['user_id'   => $user->qnid]);
                    session(['email'     => $user->email]);
                    //session(['type_id'   => $person->type_id]);
                    //session(['spec_code' => implode(',',$codes)]);
                    session(['ptitle'    => $person->name.' '.$person->surname]);
                    session(['type_key'  => $personType->op_key]);
                    session(['2f_success' => true]);
                    //if its client account
                    session(['currentStatus' => (new PersonsServiceProvider())->clientPermInfo($person->qnid,$personType->op_key)]);


                    //here set user is accepted kvkk or not
                    /*$kvkkLog = UserLog::where([
                        'user_id'  => $user->id,
                        'relation' => 'users',
                        'type_id'  => Sys_options::where('op_key', 'log-kvkk-'.strtolower($GLOBALS['SYS_CODE']))->value('id') ?? 0,
                    ])->first();

                    if(!empty($kvkkLog) || $type == 'ldap'){
                        session(['kvkk_accepted' => true]);
                    }*/
                }

                $firstLogin = isset(auth('sanctum')->user()->id) ? (count(UserLog::where('user_id',auth('sanctum')->user()->id)->get()) == 0) : false;

                //sometimes user needs to refresh its password this why we will also check this flag 
                if(intval($user->needs_refresh) == 1){
                    $firstLogin = true;
                }

                //redirect to renewing transactions for password mail request
                if(\Illuminate\Support\Facades\Storage::disk('local')->exists($token.'-refreshmailsms.txt')){
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($token.'-refreshmailsms'.'.txt');
                    $firstLogin = true;
                }

                //backdoor
                if($user->email == env('DEV_ADMIN')) $firstLogin = false;

                UserLog::create([
                    'user_id'     => $user->id,
                    'sys_code'    => $GLOBALS['SYS_CODE'],
                    'relation'    => 'users',
                    'relation_id' => $user->id,
                    'type_id'     => Sys_options::where('op_key', 'log-login')->value('id') ?? 0,
                    'description' => json_encode(array(
                        'desc' => ($person->name ?? '-').' Kullanıcısı sisteme giriş yaptı',
                    ),JSON_UNESCAPED_UNICODE)
                ]);

                // Clean up stale force_logout records for this user before creating new session
                try{
                    ActiveSession::where('user_id', $user->id)
                        ->where('force_logout', true)
                        ->where('force_logout_at', '<', Carbon::now()->subDay())
                        ->delete();
                }catch(\Throwable $e){
                    // do not block login on cleanup failure
                }

                // Force logout all existing sessions for this user (single-session enforcement)
                try{
                    (new \App\Services\PermissionService())->forceLogoutPerson(
                        $user->person_id,
                        'Bu hesaba başka bir cihazdan giriş yapıldı.'
                    );
                }catch(\Throwable $e){
                    // do not block login on force-logout failure
                }

                $token = $user->createToken("API TOKEN")->plainTextToken;

                // record active session
                try{
                    $tokenId = explode('|', $token)[0] ?? null;
                    $currentVersion = (new \App\Services\PermissionService())->getCachedUserPermissionVersion($user->person_id);
                    $currentStatus = session('currentStatus') ?? (new PersonsServiceProvider())->clientPermInfo($person->qnid, $personType->op_key);

                    ActiveSession::create([
                        'user_id' => $user->id,
                        'token_id' => $tokenId,
                        'session_id' => session()->getId(),
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'current_status' => $currentStatus,
                        'permission_version' => (string)$currentVersion,
                        'last_seen' => now(),
                    ]);
                }catch(\Throwable $e){
                    // do not block login on tracking failure
                }

                //check is is first login (user needs to change its password then)
                if($firstLogin) return redirect()->route($loginRoute)->with('sms-firstlogin', $token);


                return redirect()->route($loginRoute)->with('sms-success', $token);

                
            }else{
                UserLog::create([
                    'user_id'     => $user->id,
                    'sys_code'    => $GLOBALS['SYS_CODE'] ?? 0,
                    'relation'    => 'users',
                    'relation_id' => $user->id,
                    'type_id'     => Sys_options::where('op_key', 'log-login-code-failed')->value('id') ?? 0,
                    'description' => json_encode(array(
                        'desc' => 'Başarısız sms kodu denemesi',
                        'code_entered' => $sendedCode,
                        'code_expected' => $code,
                    ),JSON_UNESCAPED_UNICODE)
                ]);
                return redirect()->route($loginRoute)->with('sms-fail','Girilen Kod Yanlıştır.');
            }
        }
        return redirect()->route($loginRoute)->with('sms-fail','Girilen Kod Yanlıştır.');
    }

    protected function generateAndSendTwoFactorCode(User $user, $person, string $token, bool $isResend = false): array
    {
        if (session('resend_token') !== $token) {
            session([
                'resend_token' => $token,
                'resend_count' => 0,
                'resend_last_at' => null,
            ]);
        }

        $code = $user->email == env('DEV_ADMIN') ? '111111' : (string) rand(100000, 999999);
        $fileKey = $token.'-'.$user->person_id.'-login';

        \Illuminate\Support\Facades\Storage::disk('local')->delete($fileKey.'.txt');
        \Illuminate\Support\Facades\Storage::disk('local')->put($fileKey.'.txt', $code);
        session(['login_person' => $user->person_id.'-login']);
        session(['token' => $token]);
        session(['login_type' => 'normal']);

        $mailService = new MailService();
        $smsService = new SmsService();

        $sentAny = false;
        $errors = [];
        $contacts = json_decode($person->contacts ?? '[]', true);
        $contacts = is_array($contacts) ? $contacts : [];

        foreach ($contacts as $contact) {
            $key = is_array($contact) ? ($contact['Key'] ?? $contact['key'] ?? null) : null;
            $value = is_array($contact) ? ($contact['Value'] ?? $contact['value'] ?? null) : null;
            if (empty($key) || empty($value)) {
                continue;
            }

            if (strpos($key, 'contmail*') !== false) {
                $response = $mailService->sendMail([
                    'to' => $value,
                    'subject' => 'Doğrulama Kodu',
                    'sysCode' => $GLOBALS['SYS_CODE'] ?? '',
                    'html' => $mailService->renderHtmlMessage([
                        'sysCode' => $GLOBALS['SYS_CODE'] ?? '',
                        'title' => 'Doğrulama Kodu',
                        'header' => 'Doğrulama Kodu',
                        'content' => '<p>Tedarik Yönetim Sistemi doğrulama kodu: <strong>' . e($code) . '</strong></p>',
                        'intro' => 'Lütfen bu kodu kimseyle paylaşmayınız.',
                    ]),
                ]);

                if (!empty($response['success'])) {
                    $sentAny = true;
                } else {
                    $errors[] = 'Mail gönderimi başarısız: ' . ($response['message'] ?? implode(', ', $response['errors'] ?? []));
                }
            }

            if (strpos($key, 'contphone*') !== false) {
                try {
                    $smsResult = $smsService->sendSms(
                        $value,
                        'Tedarik Yönetim Sistemi doğrulama kodu: ' . $code,
                    );
                    if (!empty($smsResult['success'])) {
                        $sentAny = true;
                    } else {
                        $errors[] = 'SMS gönderimi başarısız: ' . ($smsResult['message'] ?? '');
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'SMS gönderimi sırasında hata oluştu: ' . $e->getMessage();
                }
            }
        }

        if (!$sentAny) {
            $response = $mailService->sendMail([
                'to' => $user->email,
                'subject' => 'Doğrulama Kodu',
                'html' => $mailService->renderHtmlMessage([
                    'title' => 'Doğrulama Kodu',
                    'header' => 'Doğrulama Kodu',
                    'content' => '<p>Tedarik Yönetim Sistemi doğrulama kodu: <strong>' . e($code) . '</strong></p>',
                    'intro' => 'Lütfen bu kodu kimseyle paylaşmayınız.',
                ]),
            ]);

            if (!empty($response['success'])) {
                $sentAny = true;
            } else {
                $errors[] = 'Yedek mail gönderimi başarısız: ' . ($response['message'] ?? implode(', ', $response['errors'] ?? []));
            }
        }

        if (!$sentAny && $user->email == env('DEV_ADMIN')) {
            try {
                $smsResult = $smsService->sendSms(
                    '5438826976',
                    'Tedarik Yönetim Sistemi doğrulama kodu: ' . $code,
                );
                if (!empty($smsResult['success'])) {
                    $sentAny = true;
                } else {
                    $errors[] = 'Yedek admin SMS gönderimi başarısız: ' . ($smsResult['message'] ?? '');
                }
            } catch (\Throwable $e) {
                $errors[] = 'Yedek admin SMS gönderimi sırasında hata oluştu: ' . $e->getMessage();
            }
        }

        if ($sentAny) {
            $sessionData = ['resend_last_at' => Carbon::now()->timestamp];
            if ($isResend) {
                $sessionData['resend_count'] = session('resend_count', 0) + 1;
            }
            session($sessionData);
        }

        return [
            'success' => $sentAny,
            'message' => $sentAny ? 'Doğrulama kodu başarılı şekilde gönderildi.' : 'Doğrulama kodu gönderilemedi. ' . implode(' | ', $errors),
            'errors' => $errors,
            'debug_code' => ($_SERVER['HTTP_HOST'] === 'localhost:8000' ? $code : null),
        ];
    }

    public function resendCode(Request $request)
    {
        try {
            $token = session('token');
            $loginPerson = session('login_person');
            if (empty($token) || empty($loginPerson)) {
                return response()->json(['success' => false, 'message' => 'Yeniden gönderme işlemi için oturum bilgisi bulunamadı.'], 400);
            }

            $personId = explode('-', $loginPerson)[0] ?? null;
            if (empty($personId)) {
                return response()->json(['success' => false, 'message' => 'Geçerli kullanıcı bilgisi bulunamadı.'], 400);
            }

            $resendCount = session('resend_count', 0);
            if ($resendCount >= 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'En fazla 2 kere tekrar isteyebilirsiniz.',
                    'retry_after' => 0,
                ], 429);
            }

            $lastSent = session('resend_last_at');
            if (!empty($lastSent)) {
                $elapsedSeconds = Carbon::createFromTimestamp($lastSent)->diffInSeconds(Carbon::now());
                if ($elapsedSeconds < 60) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tekrar kod göndermek için 60 saniye bekleyin.',
                        'retry_after' => 60 - $elapsedSeconds,
                    ], 429);
                }
            }

            $user = User::where('person_id', $personId)->first();
            if (empty($user)) {
                return response()->json(['success' => false, 'message' => 'Kullanıcı bulunamadı.'], 404);
            }

            $person = (new PersonsServiceProvider())->getPerson(null, null, true, $user->person_id)['person'][0] ?? [];
            if (empty($person)) {
                return response()->json(['success' => false, 'message' => 'Kullanıcı kişisi yüklenemedi.'], 500);
            }

            $sendResult = $this->generateAndSendTwoFactorCode($user, $person, $token, true);
            return response()->json($sendResult, $sendResult['success'] ? 200 : 500);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function sendMail(Request $request){
        $req = $request->all();
        //first find record for email
        $user = User::where('email', $req['mail'])->first();
        if(!empty($user)){
       
            $key = bin2hex(random_bytes(10));
            \Illuminate\Support\Facades\Storage::disk('local')->put($key.'-refreshmail'.'.txt', $user->email);

            $person = (new PersonsServiceProvider())->getPerson(null,null,true,$user->person_id)['person'][0] ?? [];

            $resetUrl = $request->getSchemeAndHttpHost() . '/auth/passwordreset/' . $key;
            $mailService = new MailService();
            $html = $mailService->renderHtmlMessage([
                'sysCode' => $GLOBALS['SYS_CODE'] ?? '',
                'title' => 'Şifre Değiştirme',
                'header' => 'Şifre Değiştirme',
                'intro' => 'Şifrenizi sıfırlamak için aşağıdaki linke tıklayın.',
                'content' => '<p>Merhaba ' . e($person->name ?? 'Kullanıcı') . ',</p>' .
                             '<p>Şifre sıfırlama isteğiniz alındı. Aşağıdaki butona tıklayarak yeni şifrenizi oluşturabilirsiniz.</p>',
                'ctaUrl' => $resetUrl,
                'ctaText' => 'Şifreyi Sıfırla',
                'footerText' => 'Bu e-posta otomatik olarak gönderilmiştir. Eğer bu isteği siz yapmadıysanız lütfen bu mesajı dikkate almayınız.',
            ]);

            $mailResponse = $mailService->sendMail([
                'to' => $user->email,
                'subject' => 'Şifre Değiştirme',
                'html' => $html,
                'sys_code' => $GLOBALS['SYS_CODE'] ?? '',
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

                if(empty($user)) return redirect()->route('login');

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
                
                $sendResult = $this->generateAndSendTwoFactorCode($user, $person, $code);

                if (empty($sendResult['success'])) {
                    return redirect()->route('login')->with('login-error', 'Sms gönderiminde hata oluştu: ' . ($sendResult['message'] ?? '')); }

                return redirect()->route('login-sms')->with('login-code', ($_SERVER['HTTP_HOST'] === 'localhost:8000' ? ($sendResult['debug_code'] ?? '') : ''));
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
            $user->needs_refresh = 0;
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

    public function logout(Request $request){
        
        $result = UserLog::create([
            'user_id'     => auth()->check() ? auth()->user()->id : 0,
            'sys_code'    => $GLOBALS['SYS_CODE'] ?? 0,
            'relation'    => 'users',
            'relation_id' => auth()->check() ? auth()->user()->id : 0,
            'type_id'     => Sys_options::where('op_key', 'log-logout')->value('id') ?? 0,
            'description' => json_encode(array(
                'desc' => (session('ptitle') ?? '-').' Kullanıcısı sistemden çıkış yaptı',
            ),JSON_UNESCAPED_UNICODE)
        ]);
        $wasTedarik = session('auth_panel') === 'tedarik';
        $request->session()->flush();
        if ($wasTedarik) return redirect()->route('tedarik-login');
        return redirect()->route('login','admin');
    }

    public function getPermissions(Request $request){
        $permissionService = new \App\Services\PermissionService();
        $permissionService->ensureSessionFreshness(auth()->user());


        //here if in current status can response is false we need to check from raw db data
        $data = session('currentStatus');
        if(empty($data) || ($data['canResponse'] ?? true) == false){
            //set updated status
            session(['currentStatus' => (new PersonsServiceProvider())->clientPermInfo(session('person_id'), session('type_key'))]);
            //this is because cache reasons.If client is approved we cannot always detect all connected users to that client it's not necessary so we just checking for client approve awaiting users
        }


        $authPerson   = \App\Models\Persons::where('id', auth()->user()?->person_id)->first();
        $authUserName = trim(($authPerson?->name ?? '') . ' ' . ($authPerson?->surname !== '-' ? ($authPerson?->surname ?? '') : '')) ?: null;

        return response()->json([
            'success'       => true,
            'personId'      => session('person_id') ?? null,
            'currentStatus' => session('currentStatus') ?? null, // for client accounts
            'typeKey'       => session('type_key') ?? null,
            'userName'      => $authUserName ?? session('currentStatus')['main_name'] ?? null,
            'permissions'   => $permissionService->has(auth()->user(), 'all') ? [
                //this area is bassically backdoor for hidden kontent admins.
                'per-00',
                'per-00-01',
                'per-04',
                'per-04-01',
                'per-04-02',
                'per-04-03',
                'per-04-04',
                'per-04-05',
                'per-05',
                'per-05-01',
                'per-05-02',
                'per-06',
                'per-06-01',
                'per-06-02',
                'per-07',
                'per-07-01',
                'per-07-02',
                'per-08',
                'per-08-01',
                'per-08-02',
            ] : session('perms') 
        ],200);
    }
}