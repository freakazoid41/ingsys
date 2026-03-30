<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <title>Kömür Tedarik Sistemi</title>
    @vite(['public/coaltheme/css/theme.css', 'resources/js/coal-swal.js'])
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

                <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
                    
                    <form method="POST" id="login-form" action="/auth/passchange"
                        class="bg-body flex-column align-items-stretch flex-center rounded-4 w-600px p-20">
                        <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20 h-100">
                            <div class="form w-100">
                                <div class="text-center mb-11">
                                    <h1 class="text-gray-900 fw-bolder mb-3">Yeni Şifre Girişi Yapınız</h1>
                                </div>
                                <div class="fv-row mb-8">
                                    <input id="in-main" required type="password" placeholder="Şifre" name="password"
                                        autocomplete="off" value="" class="form-control bg-transparent login-item" />
                                </div>
                                <div id="err-password" class="alert alert-danger mt-2 d-flex align-items-center p-2"
                                    style="display:none !important">
                                    <i class="ki-duotone ki-shield-tick fs-2hx text-danger me-4"><span
                                            class="path1"></span><span class="path2"></span></i>

                                    <div class="d-flex flex-column">
                                        <h5 class="mb-1 text-danger">Şifreniz en az bir tane büyük/küçük harf, rakam
                                            ve özel karakter
                                            içermelidir ve 8 karakterden oluşmalıdır.</h5>
                                    </div>
                                </div>
                                <div class="fv-row mb-8">
                                    <input required id="in-check" type="password" placeholder="Şifre Tekrar"
                                        name="password-check" autocomplete="off" value=""
                                        class="form-control bg-transparent login-item" />
                                </div>
                                <div class="alert alert-danger mt-2 d-flex align-items-center p-2 message"
                                    style="display:none !important">
                                    <i class="ki-duotone ki-shield-tick fs-2hx text-danger me-4"><span
                                            class="path1"></span><span class="path2"></span></i>

                                    <div class="d-flex flex-column">
                                        <h5 class="mb-1 text-danger">Şifreniz en az bir tane büyük/küçük harf, rakam
                                            ve özel karakter
                                            içermelidir.</h5>
                                    </div>
                                </div>



                                <div class="d-grid mb-10">
                                    <button type="button" id="kt_sign_in_submit" class="btn btn-primary">
                                        <span class="indicator-label">Şifre Güncelle</span>
                                        <span class="indicator-progress">Lütfen Bekleyiniz
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>
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
