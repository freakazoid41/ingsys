<!DOCTYPE html>
<html lang="tr" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Gdz - Sipariş Platformu</title>
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
    .wrap {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .card {
        background: #ffffff;
        border-radius: 14px;
        width: 100%;
        max-width: 560px;
        min-height: 840px;
        padding: 64px 48px 40px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 8px 40px rgba(0,0,0,0.07);
        border: 1px solid rgba(0,0,0,0.04);
    }
    .logo {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 0;
    }
    .logo img {
        height: 140px;
        width: auto;
        display: block;
    }
    .title {
        text-align: center;
        margin-top: 36px;
        font-size: 20px;
        font-weight: 600;
        color: #2b2b2e;
        letter-spacing: -0.1px;
    }
    .form-area {
        margin-top: 72px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        flex: 1;
    }
    .field {
        position: relative;
        width: 100%;
    }
    .input {
        width: 100%;
        height: 56px;
        border: 1px solid #e7e7e9;
        border-radius: 10px;
        background: #fff;
        padding: 0 18px;
        font-size: 15px;
        color: #1a1a1e;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
        box-sizing: border-box;
    }
    .input::placeholder { color: transparent; }
    .input:focus { border-color: #FF4A15; box-shadow: 0 0 0 3px rgba(255,74,21,0.08); }
    .field label {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        background: #fff;
        padding: 0 6px;
        color: #9a9aa3;
        font-size: 15px;
        font-weight: 400;
        pointer-events: none;
        transition: all .18s ease;
        line-height: 1;
        white-space: nowrap;
    }
    .field .input:focus + label,
    .field .input:not(:placeholder-shown) + label,
    .field .input:-webkit-autofill + label {
        top: 0;
        font-size: 12.5px;
        font-weight: 500;
        color: #FF4A15;
    }
    .field .input:not(:focus):not(:placeholder-shown) + label {
        color: #7a7a80;
    }
    .btn-main {
        margin-top: 26px;
        width: 100%;
        height: 58px;
        background: #FF4713;
        color: #fff;
        border: none;
        border-radius: 11px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s, transform .08s;
        letter-spacing: 0.2px;
    }
    .btn-main:hover { background: #e93f11; }
    .btn-main:active { transform: scale(0.99); }
    .btn-main:disabled { opacity: .7; cursor: not-allowed; }
    .btn-main .spinner-border { display: none; width: 18px; height: 18px; border-width: 2px; }
    .bottom {
        margin-top: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .check {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #7a7a80;
        font-size: 12px;
        cursor: pointer;
        user-select: none;
    }
    .check input {
        width: 13px;
        height: 13px;
        border-radius: 2px;
        border: 1px solid #d2d2d6;
        appearance: none;
        -webkit-appearance: none;
        background: #fff;
        position: relative;
        cursor: pointer;
    }
    .check input:checked {
        background: #FF4713;
        border-color: #FF4713;
    }
    .check input:checked::after {
        content: '';
        position: absolute;
        left: 3px;
        top: 1px;
        width: 4px;
        height: 7px;
        border: solid #fff;
        border-width: 0 1.6px 1.6px 0;
        transform: rotate(45deg);
    }
    .forgot {
        color: #7a7a80;
        font-size: 12px;
        text-decoration: none;
        font-weight: 400;
    }
    .forgot:hover { color: #2b2b2e; text-decoration: underline; }
    .video-row {
        margin-top: auto;
        padding-top: 28px;
        display: flex;
    }
    .video-btn {
        width: 28px;
        height: 20px;
        border: 1.4px solid #b8b8bd;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #9a9aa3;
        position: relative;
        background: transparent;
        cursor: pointer;
        text-decoration: none;
    }
    .video-btn::after {
        content: '';
        position: absolute;
        right: -7px;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 6px solid #b8b8bd;
        border-top: 4px solid transparent;
        border-bottom: 4px solid transparent;
    }
    .video-btn svg { width: 12px; height: 12px; }
    .alert { margin-top: 10px; font-size: 12.5px; border-radius: 8px; padding: 10px 12px; }
    #kt_sign_in_submit>* { pointer-events: none; }
    /* recaptcha centering tweak */
    .g-recaptcha > div { margin: 0 auto; }
</style>
<body>
    <div class="wrap">
        <div class="card">
            <div class="logo">
                <img src="/coaltheme/GDZ.svg" alt="Gdz" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                <!-- fallback if SVG missing -->
                <svg style="display:none" width="84" height="28" viewBox="0 0 110 38" fill="none"><text x="0" y="28" font-family="Inter,sans-serif" font-size="30" font-weight="800" fill="#FF4713">Gdz</text><g transform="translate(68,2)"><path d="M6 4 L22 0 L22 8 L6 12 Z" fill="#FF4713"/><path d="M6 14 L22 10 L22 18 L6 22 Z" fill="#FF4713"/><path d="M24 4 L38 12 L24 20 Z" fill="#FF4713"/></g></svg>
            </div>
            <div class="title">Sipariş Platformu</div>

            <form id="login-form" class="form-area" method="POST" action="{{ route('login-user','tedarik', false) }}" novalidate>
                @csrf
                <input type="hidden" name="auth_panel" value="tedarik" />

                <div class="field">
                    <input required type="text" id="email" name="email" autocomplete="off" placeholder=" " class="input login-item" />
                    <label for="email">E-Posta Adresi</label>
                </div>
                <div class="field">
                    <input required type="password" id="password" name="password" autocomplete="off" placeholder=" " class="input login-item" />
                    <label for="password">Şifreniz</label>
                </div>

                @php $recaptchaSiteKey = config('services.recaptcha.site_key') ?: env('RECAPTCHA_SITE_KEY'); @endphp
                @if($recaptchaSiteKey)
                    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                    <div style="display:flex; justify-content:center; margin-top:2px;">
                        <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                    </div>
                @endif

                @if (\Session::has('login-error'))
                    <div class="alert alert-danger" style="margin-top:6px;">{!! \Session::get('login-error') !!}</div>
                @endif
                @if (\Session::has('auth-forgot'))
                    <div class="alert alert-info" style="margin-top:6px;">{!! \Session::get('auth-forgot') !!}</div>
                @endif
                @if (\Session::has('sms-fail'))
                    <div class="alert alert-danger" style="margin-top:6px;">{!! \Session::get('sms-fail') !!}</div>
                @endif

                @if (\Session::has('sms-firstlogin'))
                    <button type="submit" id="kt_sign_in_submit" class="btn-main" style="margin-top:10px;">
                        <span class="indicator-label">Şifre Güncelleme Ekranına Yönlendiriliyor..</span>
                        <span class="spinner-border spinner-border-sm ms-2"></span>
                    </button>
                    <input name="apiKey" hidden readonly value="{{\Session::get('sms-firstlogin')}}">
                    <input name="firstLogin" hidden readonly value="{{\Session::get('sms-firstlogin')}}">
                @elseif (\Session::has('sms-success'))
                    <button type="submit" id="kt_sign_in_submit" class="btn-main" style="margin-top:10px;">
                        <span class="indicator-label">Giriş Başarılı..</span>
                        <span class="spinner-border spinner-border-sm ms-2"></span>
                    </button>
                    <input name="apiKey" hidden readonly value="{{\Session::get('sms-success')}}">
                    @if(!empty($targetModule))
                        <input name="targetModule" hidden readonly value="{{$targetModule}}">
                    @endif
                @else
                    <button type="button" id="submit-button" class="btn-main">
                        <span class="indicator-label">Giriş Yap</span>
                        <span class="spinner-border spinner-border-sm ms-2"></span>
                    </button>
                @endif

                @if(!\Session::has('sms-success') && !\Session::has('sms-firstlogin'))
                <div class="bottom">
                    <label class="check">
                        <input type="checkbox" id="remember" />
                        <span>Beni Hatırla</span>
                    </label>
                    <a href="javascript:;" id="btn-forget" class="forgot">Şifremi unuttum</a>
                </div>
                @else
                <div style="height:18px"></div>
                @endif
            </form>

            <div class="video-row">
                <a href="javascript:;" class="video-btn" title="Tanıtım Videosu" aria-label="Video">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="12" height="12" rx="1.5"></rect><path d="M15 9l4-2.5v11L15 15z"></path></svg>
                </a>
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
