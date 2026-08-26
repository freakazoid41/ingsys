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
                        <form id="login-form" class="form w-100" method="POST" action="{{route('register-user',[],false)}}" novalidate="novalidate">
                            @csrf
                            <div class="text-center mb-11">
                                <h1 class="text-gray-900 fw-bolder mb-3">Kayıl Ol</h1>
                            </div>
                            <div class="separator separator-content my-14">
                                <span class="w-125px text-gray-500 fw-semibold fs-7">Kullanıcı Bilgileriniz</span>
                            </div>
                            <div class="fv-row mb-8">
                                <input required type="email" id="email" placeholder="Kullancı E-Posta Adresi" value="" name="email" autocomplete="off"
                                    class="form-control bg-transparent login-item" />
                            </div>

                            <div id="err-email" class="alert alert-danger mt-2 d-flex align-items-center p-2"
                                style="display:none !important">
                                <i class="ki-duotone ki-shield-tick fs-2hx text-danger me-4"><span
                                        class="path1"></span><span class="path2"></span></i>

                                <div class="d-flex flex-column">
                                    <h5 class="mb-1 text-danger">Lütfen geçerli bir mail adresi giriniz.</h5>
                                </div>
                            </div>
                            <div class="fv-row mb-8">
                                <input required type="text" id="phone" placeholder="Telefon" value="" name="phone" autocomplete="off"
                                    class="form-control bg-transparent login-item" />
                            </div>
                            <div id="err-phone" class="alert alert-danger mt-2 d-flex align-items-center p-2"
                                style="display:none !important">
                                <i class="ki-duotone ki-shield-tick fs-2hx text-danger me-4"><span
                                        class="path1"></span><span class="path2"></span></i>

                                <div class="d-flex flex-column">
                                    <h5 class="mb-1 text-danger">Lütfen telefon alanını doldurunuz</h5>
                                </div>
                            </div>

                            <div class="fv-row mb-8">
                                <div class="input-group">
                                    <input required type="password" id="password" value="" placeholder="Şifre" name="password" autocomplete="off"
                                        class="form-control bg-transparent login-item pe-10" />
                                    <span class="input-group-text password-toggle bg-transparent border-start-0" style="cursor: pointer;" data-target="password">
                                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                            <path d="M12 5c-7 0-11 6-11 7s4 7 11 7 11-6 11-7-4-7-11-7zm0 12c-3.9 0-7-2.8-7-5s3.1-5 7-5 7 2.8 7 5-3.1 5-7 5zm0-8.5c-1.9 0-3.5 1.6-3.5 3.5S10.1 15.5 12 15.5 15.5 13.9 15.5 12 13.9 8.5 12 8.5z"/>
                                        </svg>
                                        <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="display:none;">
                                            <path d="M12 5c-7 0-11 6-11 7 1.3 2.2 3.3 4.3 6.3 5.5l-2.4 2.4 1.4 1.4 16-16-1.4-1.4-3.1 3.1C16.1 7.1 14.1 6 12 6c-4.1 0-7.6 2.5-9 6 1.1 2.2 3 4.1 5.4 5.1l-1.4 1.4C2.6 16.8 1 14.2 1 12c0-1.6.7-3.3 2.2-5 1.8-1.9 4.6-3 7.8-3 2 0 4 .5 5.7 1.4l-1.5 1.5C14.6 5.5 13.3 5 12 5zm9.7 4.7c-.4-.7-.9-1.4-1.6-2L8.8 18.1c.7.4 1.5.7 2.3.7 4.1 0 7.6-2.5 9-6-0.1-.3-0.3-0.8-0.4-1.1z"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="fv-row mb-8">
                                <div class="input-group">
                                    <input required type="password" id="password-check" value="" placeholder="Şifre Tekrar" name="password" autocomplete="off"
                                        class="form-control bg-transparent login-item pe-10" />
                                    <span class="input-group-text password-toggle bg-transparent border-start-0" style="cursor: pointer;" data-target="password-check">
                                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                            <path d="M12 5c-7 0-11 6-11 7s4 7 11 7 11-6 11-7-4-7-11-7zm0 12c-3.9 0-7-2.8-7-5s3.1-5 7-5 7 2.8 7 5-3.1 5-7 5zm0-8.5c-1.9 0-3.5 1.6-3.5 3.5S10.1 15.5 12 15.5 15.5 13.9 15.5 12 13.9 8.5 12 8.5z"/>
                                        </svg>
                                        <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="display:none;">
                                            <path d="M12 5c-7 0-11 6-11 7 1.3 2.2 3.3 4.3 6.3 5.5l-2.4 2.4 1.4 1.4 16-16-1.4-1.4-3.1 3.1C16.1 7.1 14.1 6 12 6c-4.1 0-7.6 2.5-9 6 1.1 2.2 3 4.1 5.4 5.1l-1.4 1.4C2.6 16.8 1 14.2 1 12c0-1.6.7-3.3 2.2-5 1.8-1.9 4.6-3 7.8-3 2 0 4 .5 5.7 1.4l-1.5 1.5C14.6 5.5 13.3 5 12 5zm9.7 4.7c-.4-.7-.9-1.4-1.6-2L8.8 18.1c.7.4 1.5.7 2.3.7 4.1 0 7.6-2.5 9-6-0.1-.3-0.3-0.8-0.4-1.1z"/>
                                        </svg>
                                    </span>
                                </div>
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
                                        karakter içermelidir. Şifre alanları uyumlu olmalıdır.</h5>
                                </div>
                            </div>

                            @if (\Session::has('register-error'))
                                <div class="alert alert-danger">
                                    {!! \Session::get('register-error') !!}
                                </div>
                            @endif

                            <div class="d-grid mb-10">
                                <button type="button" id="submit-button" class="btn btn-primary">
                                    <span class="indicator-label">Kayıt Ol</span>
                                    <span class="indicator-progress">
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                            <div class="d-grid mb-10">
                                <a href="/" type="button" class="btn btn-primary">
                                    <span class="indicator-label">Giriş Yap</span>
                                    <span class="indicator-progress">
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </a>
                            </div>


                        </form>
                    </div>
                    
                    <div class="d-flex flex-stack px-lg-10">
                        <div class="d-flex fw-semibold text-primary fs-base gap-5">
                            <a href="#" target="_blank">Gizlilik Politikası</a>
                        </div>
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
    </script>
</body>

</html>
