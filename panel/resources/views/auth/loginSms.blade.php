<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
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
                        <form class="form w-100 mb-13" method="POST" id="login-form" action="/api/auth/checkcode">
                            @csrf
                            <div class="text-center mb-10">
                                <svg xmlns="http://www.w3.org/2000/svg" width="162" height="162" viewBox="0 0 162 162" fill="none">
                                    <path d="M49.4498 17.217C48.1718 17.217 46.9262 17.667 46.046 18.5454C45.164 19.4202 44.714 20.6748 44.714 21.9492V140.123C44.714 141.397 45.164 142.643 46.046 143.525C46.9262 144.407 48.1718 144.857 49.4498 144.857H112.666C113.94 144.857 115.197 144.407 116.068 143.525C116.952 142.643 117.4 141.397 117.4 140.123V21.9492C117.4 20.6748 116.95 19.4202 116.068 18.5454C115.197 17.6652 113.94 17.217 112.666 17.217H102.906C102.138 17.217 101.454 17.676 101.164 18.387L99.7094 21.9492C99.2396 23.1138 98.867 23.6844 98.273 24.0696C97.6934 24.4638 96.662 24.7914 94.637 24.7914H67.475C65.4518 24.7914 64.4204 24.462 63.839 24.0696C63.2486 23.6862 62.8724 23.1156 62.4044 21.9492L60.9518 18.387C60.6674 17.6832 59.9834 17.217 59.2256 17.217H49.4498Z" fill="#9EDCFF"/>
                                    <path d="M49.1649 13.176H112.946C117.55 13.176 121.185 16.83 121.185 21.4254V140.647C121.185 145.24 117.549 148.898 112.946 148.898H49.1649C44.5641 148.898 40.9245 145.24 40.9245 140.647V21.4254C40.9263 16.8318 44.5641 13.176 49.1649 13.176ZM49.1649 9.44458C42.5589 9.44458 37.1841 14.8194 37.1841 21.4254V140.647C37.1841 147.256 42.5589 152.627 49.1649 152.627H112.946C119.556 152.627 124.936 147.256 124.936 140.647V21.4254C124.936 14.8194 119.556 9.44458 112.946 9.44458H49.1649Z" fill="#7E94B5"/>
                                    <path d="M75.0235 17.217C73.9813 17.217 73.1497 18.0486 73.1497 19.0908C73.1497 20.1312 73.9813 20.9664 75.0235 20.9664H88.0357C89.0671 20.9664 89.9113 20.1312 89.9113 19.0908C89.9113 18.0486 89.0671 17.217 88.0357 17.217H75.0235Z" fill="#B5B5C3"/>
                                    </svg>
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
                        <div class="text-center fw-semibold fs-5">
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
