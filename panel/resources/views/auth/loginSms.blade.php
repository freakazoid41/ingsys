<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <title>Kömür Tedarik Sistemi</title>
    @vite(['public/coaltheme/css/theme.css'])

</head>
<style>
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
        border: 3px solid #ff671d
    }

    h2 {
        color: #ff671d;
        font-weight: bold;
        font-size: 24px;
    }

</style>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed aside-fixed aside-secondary-disabled">

    <div class="d-flex flex-column flex-root">
        <div class="d-flex flex-column flex-column-fluid flex-lg-row">
            <div class="d-flex flex-center w-lg-50 pt-15 pt-lg-0 px-10">
                <div class="d-flex flex-center flex-lg-start flex-column">
                    <a href="" class="mb-7">
                        <img alt="Logo" src="/system/front/media/logos/adm-logo.svg" width="300px" />
                    </a>
                    <h2 class="m-0">Kömür Tedarik Sistemi</h2>
                </div>
            </div>
            <div
                class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
                <div id="div-login"
                    class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-600px p-20">
                    <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
                        <form class="form w-100 mb-13" method="POST" id="login-form" action="/api/auth/checkcode">
                            @csrf
                            <div class="text-center mb-10">
                                <img alt="Logo" class="mh-125px" src="/system/front/media/svg/misc/smartphone-2.svg" />
                            </div>
                            <div class="text-center mb-10">
                                <h1 class="text-gray-900 mb-3">Mail Doğrulama</h1>
                                <div class="text-muted fw-semibold fs-5 mb-5">Sistemde kayıtlı olan e-posta adresinize
                                    mail
                                    gönderilmiştir.</div>
                                <div class="phoneMask fw-bold text-gray-900 fs-3"></div>
                                <span class="countdown" id="spn-tm"></span>
                                {{-- <span class="countdown"></span> --}}
                            </div>
                            <div class="mb-10">
                                <div class="fw-bold text-start text-gray-900 fs-6 mb-1 ms-1">6 karakterli güvenlik
                                    kodunu giriniz
                                </div>
                                <div class="d-flex">
                                    @for($i = 1 ; $i
                                    <= 6;$i++) <input type="text" required name="code_{{$i}}" maxlength="1"
                                        data-step="{{$i}}" pattern="\d*"
                                        class="form-control send-code bg-transparent h-60px p-0 p-sm-1 col fs-2qx text-center mx-1 my-2 sms-code"
                                        value="" />
                                    @endfor
                                </div>
                            </div>
                            <div class="d-grid mb-10">
                                <style>
                                    #btn-next>* {
                                        pointer-events: none !important
                                    }

                                </style>
                                <button type="button" id="btn-next" class="btn btn-primary">
                                    <span class="indicator-label">Kodu Onayla</span>
                                    <span class="indicator-progress">Lütfen Bekleyiniz
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                        <div class="text-center" style="font-size:50px" id="countdown">
                            25
                        </div>
                        <div hidden class="text-center fw-semibold fs-5">
                            <span class="text-muted me-1">Mesaj gelmedi mi?</span>
                            <a href="javascript:;" id="btn-send-code" class="link-primary fs-5 me-1">Tekrar Gönder</a>
                        </div>
                    </div>

                    <div class="d-flex flex-stack px-lg-10">
                        <div class="d-flex fw-semibold text-primary fs-base gap-5">
                            <a href="#" target="_blank">Gizlilik Politikası</a>
                        </div>
                        <div class="d-flex fw-semibold text-primary fs-base gap-5">
                            <a href="javascript:;" id="btn-forget">Şifremi Unuttum !</a>
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
