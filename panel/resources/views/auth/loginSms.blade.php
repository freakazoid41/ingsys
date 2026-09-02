<!DOCTYPE html>
<html lang="tr" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Gdz - Doğrulama Kodu</title>
    @vite(['public/coaltheme/css/theme.css', 'resources/js/coal-swal.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<style>
    * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    html, body { height: 100%; }
    body {
        margin: 0;
        background: #f2f2f3;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .wrap { width: 100%; display: flex; justify-content: center; align-items: center; }
    .card {
        background: #ffffff;
        border-radius: 14px;
        width: 100%;
        max-width: 560px;
        min-height: 720px;
        padding: 48px 44px 32px;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        box-shadow: 0 8px 40px rgba(0,0,0,0.07);
        border: 1px solid rgba(0,0,0,0.04);
    }
    .logo { display: flex; justify-content: center; align-items: center; }
    .logo img { height: 110px; width: auto; display: block; }
    .title {
        text-align: center;
        margin-top: 18px;
        font-size: 20px;
        font-weight: 700;
        color: #1a1a1e;
        letter-spacing: -0.2px;
    }
    .subtitle {
        text-align: center;
        margin-top: 8px;
        font-size: 13px;
        color: #7a7a80;
        line-height: 1.5;
    }
    .countdown-big {
        text-align: center;
        margin-top: 14px;
        font-size: 44px;
        font-weight: 700;
        color: #FF4713;
        letter-spacing: 1px;
        line-height: 1;
    }
    .code-label {
        text-align: center;
        margin-top: 22px;
        font-size: 12.5px;
        font-weight: 600;
        color: #2b2b2e;
    }
    .code-row {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin-top: 10px;
    }
    .code-input {
        width: 52px;
        height: 52px;
        border: 1.5px solid #e7e7e9;
        border-radius: 10px;
        background: #fff;
        text-align: center;
        font-size: 20px;
        font-weight: 700;
        color: #1a1a1e;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .code-input:focus { border-color: #FF4713; box-shadow: 0 0 0 3px rgba(255,71,19,0.10); }
    .btn-main {
        margin-top: 22px;
        width: 100%;
        height: 52px;
        background: #FF4713;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s;
    }
    .btn-main:hover { background: #e93f11; }
    .btn-main .indicator-progress { display: none; margin-left: 8px; }
    .resend-row {
        text-align: center;
        margin-top: 16px;
        font-size: 13px;
        color: #7a7a80;
    }
    .resend-row a { color: #FF4713; font-weight: 600; text-decoration: none; }
    .resend-row a:hover { text-decoration: underline; }
    .resend-row a[disabled] { opacity: .5; pointer-events: none; }
    .footer-links {
        margin-top: auto;
        padding-top: 24px;
        display: flex;
        justify-content: center;
        gap: 18px;
        font-size: 12px;
    }
    .footer-links a { color: #9a9aa3; text-decoration: none; }
    .footer-links a:hover { color: #2b2b2e; }
    #btn-next>* { pointer-events: none; }
</style>
<body>
    <div class="wrap">
        <div class="card">
            <div class="logo">
                <img src="/coaltheme/GDZ.svg" alt="Gdz">
            </div>
            <div class="title">Doğrulama Kodu</div>
            <div class="subtitle">Sistemde kayıtlı e-posta adresinize<br>doğrulama kodu gönderildi.</div>

            <div class="countdown-big" id="countdown">120</div>

            <form class="w-100" method="POST" id="login-form" action="/api/auth/checkcode">
                @csrf
                <div class="code-label">6 karakterli güvenlik kodunu giriniz</div>
                <div class="code-row">
                    @for($i = 1; $i <= 6; $i++)
                        <input type="text" required name="code_{{$i}}" maxlength="1" data-step="{{$i}}" pattern="\d*" class="code-input send-code" value="" autocomplete="off" inputmode="numeric" />
                    @endfor
                </div>

                <button type="button" id="btn-next" class="btn-main">
                    <span class="indicator-label">Kodu Onayla</span>
                    <span class="indicator-progress"><span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </form>

            <div class="resend-row">
                <span>Mesaj gelmedi mi?</span>
                <a href="javascript:;" id="btn-send-code">Tekrar Gönder</a>
            </div>

            <div class="footer-links">
                <a href="#" target="_blank">Gizlilik Politikası</a>
                <a href="javascript:;" id="btn-forget">Şifremi Unuttum</a>
            </div>
        </div>
    </div>

    <?php foreach($scripts as $s): ?>
        <script src="<?php echo $s.'?v='.date('YmdHi'); ?>" referrerpolicy="origin"></script>
    <?php endforeach; ?>
    <script type="module">
        import Page from '<?= $pageScript.'?v='.date('YmdHi') ?>';
        const page = new Page();
    </script>
</body>
</html>
