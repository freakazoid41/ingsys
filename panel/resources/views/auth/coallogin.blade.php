
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Tedarik Yönetim Sistemi</title>
    @vite(['public/coaltheme/css/theme.css', 'resources/js/coal-swal.js'])
</head>
<style>
    a{
        color: #154B91
    }
    #kt_sign_in_submit>* {
        pointer-events: none;
        
    }
    body {
        background: url(/coaltheme/media/login-background.svg);
        background-position: bottom;
        background-repeat: no-repeat;
        background-size: 100%;
    }

    .bg-body {
        border: 3px solid #154B91;
    }

    h2 {
        color: #154B91;
        font-weight: bold;
        font-size: 24px;
    }
    .video-row{
        display:flex;
        margin-top: 12px;
        padding-top: 14px;
        border-top: 1px solid #eef2f7;
    }
    .video-btn{
        width:52px; height:38px;
        border:1px solid #e2e8f0; border-radius:9px;
        display:inline-flex; align-items:center; justify-content:center;
        color:#64748b; position:relative; background:#fff; cursor:pointer; text-decoration:none; padding:0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06); transition: all .2s ease;
    }
    .video-btn svg{ width:22px; height:22px; display:block; }
    .video-btn:hover{ color:#154B91; border-color:#154B91; background:#eff6ff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(21,75,145,0.15); }

</style>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed aside-fixed aside-secondary-disabled">

    <div class="d-flex flex-column flex-root">
       
        <div class="d-flex flex-column flex-column-fluid flex-lg-row">
            <div class="d-flex flex-center w-lg-50 pt-15 pt-lg-0 px-10">
                <div class="d-flex flex-center flex-lg-start flex-column align-items-center">
                    <a href="" class="mb-7">
                        <img alt="Logo" src="/coaltheme/{{$GLOBALS['SYS_CODE']}}.svg" width="300px" />
                    </a>
                    <h2 class="m-0">Tedarik Yönetim Sistemi</h2>
                </div>
            </div>
            <div
                class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
                <div id="div-login"
                    class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-600px p-20">
                    <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
                        <form id="login-form" class="form w-100" method="POST" action="{{ route('login-user','admin', false) }}" novalidate="novalidate">
                            @csrf
                            <div class="text-center mb-11">
                                <h1 class="text-gray-900 fw-bolder mb-3">Giriş Yapınız</h1>
                            </div>
                            <div class="separator separator-content my-14">
                                <span class="w-125px text-gray-500 fw-semibold fs-7">Kullanıcı Bilgileriniz</span>
                            </div>
                            <div class="fv-row mb-8">
                                <input required type="text" id="email" placeholder="Kullancı Adı" value="" name="email" autocomplete="off"
                                    class="form-control bg-transparent login-item" />
                            </div>

                            <div id="err-email" class="alert alert-danger mt-2 d-flex align-items-center p-2"
                                style="display:none !important">
                                <i class="ki-duotone ki-shield-tick fs-2hx text-danger me-4"><span
                                        class="path1"></span><span class="path2"></span></i>

                                <div class="d-flex flex-column">
                                    <h5 class="mb-1 text-danger">Lütfen kullanıcı adı alanını doldurunuz</h5>
                                </div>
                            </div>

                            <div class="fv-row mb-8">
                                <input required type="password" id="password" value="" placeholder="Şifre" name="password" autocomplete="off"
                                    class="form-control bg-transparent login-item" />
                            </div>
                            
                            @php $recaptchaSiteKey = config('services.recaptcha.site_key') ?: env('RECAPTCHA_SITE_KEY'); @endphp
                            @if($recaptchaSiteKey)
                                <script src="https://www.google.com/recaptcha/api.js" async defer></script>

                                <div class="mb-3">
                                    <div class="g-recaptcha mt-2 d-flex justify-content-center" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                                </div>
                            @endif
                            <div id="err-password" class="alert alert-danger mt-2 d-flex align-items-center p-2"
                                style="display:none !important">
                                <i class="ki-duotone ki-shield-tick fs-2hx text-danger me-4"><span
                                        class="path1"></span><span class="path2"></span></i>

                                <div class="d-flex flex-column">
                                    <h5 class="mb-1 text-danger">Şifreniz en az bir tane büyük/küçük harf, rakam ve özel
                                        karakter içermelidir.</h5>
                                </div>
                            </div>

                            

                          

                            @if (\Session::has('login-error'))
                            <div class="alert alert-danger">
                                {!! \Session::get('login-error') !!}
                            </div>
                            @endif

                            @if (\Session::has('auth-forgot'))
                            <div class="alert alert-info">
                                {!! \Session::get('auth-forgot') !!}
                            </div>
                            @endif

                            @if (\Session::has('sms-fail'))
                            <div class="alert alert-danger">
                                {!! \Session::get('sms-fail') !!}
                            </div>
                            @endif


                            @if (\Session::has('sms-firstlogin'))

                                <div class="d-grid mb-10">
                                    <button type="submit" id="kt_sign_in_submit" class="btn btn-primary">
                                        <span class="indicator-label">Giriş Başarılı Şifre Güncelleme Ekranına Yönlendiriliyor..</span>
                                        <span style="display: block !important"  class="indicator-progress">Lütfen Bekleyiniz
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                                <input name="apiKey"     hidden readonly value= "{{\Session::get('sms-firstlogin')}}">
                                <input name="firstLogin" hidden readonly value= "{{\Session::get('sms-firstlogin')}}">

                            @elseif (\Session::has('sms-success'))
                                <div class="d-grid mb-10">
                                    <button type="submit" id="kt_sign_in_submit" class="btn btn-primary">
                                        <span class="indicator-label">Giriş Başarılı Ana Sayfaya Yönlendiriliyor..</span>
                                        <span style="display: block !important"  class="indicator-progress">Lütfen Bekleyiniz
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                                <input name="apiKey" hidden readonly value= "{{\Session::get('sms-success')}}">
                            @else
                                <div class="d-grid mb-10">
                                    <button type="button" id="submit-button" class="btn btn-primary">
                                        <span class="indicator-label">Giriş yap</span>
                                        <span class="indicator-progress">
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                                <div class="d-grid mb-10">
                                    <a href="{{ route('register') }}" class="btn btn-primary">
                                        <span class="indicator-label">Kayıt Ol</span>
                                        <span class="indicator-progress">
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </a>
                                </div>
                            @endif
                            


                        </form>
                    </div>
                    
                    <div class="d-flex flex-stack px-lg-10">
                        <div class="d-flex fw-semibold text-primary fs-base gap-5">
                            <a href="#" target="_blank">Gizlilik Politikası</a>
                        </div>
                        <div class="d-flex fw-semibold text-primary fs-base gap-5">
                            <a href="javascript:;" id="btn-forget">Şifremi Unuttum !</a>
                        </div>

                    </div>
                    <div class="video-row px-lg-10">
                        <a href="javascript:;" class="video-btn" title="Tanıtım Videosu" aria-label="Tanıtım Videosunu İzle" data-video="/coaltheme/demoVideos/loginVideo.mp4">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9A2.25 2.25 0 004.5 18.75z"/></svg>
                        </a>
                    </div>
                </div>

            </div>



        </div>
    </div>
    <?php foreach($scripts as $s): ?>
        <script src="<?php echo $s.'?v='.date('YmdHi'); ?>" referrerpolicy="origin"></script>
    <?php endforeach; ?>

    <script type="module">
        import Page from '<?= $pageScript.'?v='.date('YmdHi') ?>';
        const page  =  new Page();
        document.querySelector('.video-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            const src = e.currentTarget.dataset.video || '/coaltheme/demoVideos/loginVideo.mp4';
            if (window.Swal) {
                Swal.fire({
                    title: 'Tanıtım Videosu',
                    html: `<div style="border-radius:12px;overflow:hidden;background:#000;"><video controls autoplay playsinline style="width:100%;max-height:62vh;display:block;"><source src="${src}" type="video/mp4"><source src="/coaltheme/demoVideos/loginVideo.mov" type="video/quicktime">Tarayıcınız video etiketini desteklemiyor.</video></div><div style="margin-top:10px;font-size:12px;color:#64748b;text-align:center;">Tedarik Yönetim Sistemi tanıtım videosu</div>`,
                    width: '860px',
                    padding: '18px 18px 14px',
                    showCloseButton: true,
                    showConfirmButton: false,
                    background: '#fff',
                    customClass: { popup: 'demo-video-popup' },
                    didClose: () => {
                        const v = Swal.getHtmlContainer()?.querySelector('video');
                        if (v) { v.pause(); v.removeAttribute('src'); v.load(); }
                    }
                });
            } else {
                window.open(src, '_blank');
            }
        });
    </script>
</body>

</html>
