<!DOCTYPE html>
<html lang="tr" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Modül Seçimi — Gdz Sipariş Platformu</title>
    @vite(['public/coaltheme/css/theme.css', 'resources/js/coal-swal.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        border-radius: 16px;
        width: 100%;
        max-width: 760px;
        padding: 40px 36px 32px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 12px 48px rgba(0,0,0,0.08);
        border: 1px solid rgba(0,0,0,0.04);
    }
    .logo { display: flex; justify-content: center; align-items: center; }
    .logo img { height: 84px; width: auto; display: block; }
    .title { text-align: center; margin-top: 16px; font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px; }
    .subtitle { text-align: center; margin-top: 8px; font-size: 13.5px; color: #64748b; line-height: 1.6; }
    .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-top: 28px;
    }
    @media (max-width: 640px){ .grid { grid-template-columns: 1fr; } }
    .mod {
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 16px;
        cursor: pointer;
        transition: all .18s ease;
        background: #fff;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        gap: 10px;
        text-align: left;
        width: 100%;
    }
    .mod:hover { border-color: var(--mc); box-shadow: 0 8px 24px rgba(0,0,0,0.06); transform: translateY(-1px); }
    .mod.disabled { opacity: .58; cursor: not-allowed; background: #f8fafc; }
    .mod.disabled:hover { transform: none; box-shadow: none; }
    .mod::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--mc); }
    .mod-top { display: flex; align-items: center; gap: 12px; }
    .mod-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 20px; flex-shrink: 0;
        background: var(--mc);
    }
    .mod-title { font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .mod-desc { font-size: 12.5px; color: #64748b; line-height: 1.5; margin-top: 2px; }
    .mod-arrow {
        margin-left: auto; width: 28px; height: 28px; border-radius: 999px;
        border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center;
        color: #64748b; font-size: 14px; background: #fff;
    }
    .mod:hover .mod-arrow { background: var(--mc); color: #fff; border-color: var(--mc); }
    .mod.disabled .mod-arrow { background: #f1f5f9; color: #94a3b8; }
    .badge-soon {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11px; font-weight: 700; letter-spacing: .04em;
        background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;
        padding: 3px 8px; border-radius: 999px; margin-top: 2px; width: fit-content;
    }
    .foot { margin-top: 18px; display: flex; justify-content: center; gap: 12px; font-size: 12.5px; color: #94a3b8; }
    .foot a { color: #64748b; text-decoration: none; font-weight: 600; }
    .foot a:hover { color: #0f172a; text-decoration: underline; }
</style>
<body>
    <div class="wrap">
        <div class="card">
            <div class="logo">
                <img src="/coaltheme/GDZ.svg" alt="Gdz" onerror="this.style.display='none'">
            </div>
            <div class="title">Modül Seçin</div>
            <div class="subtitle">Yetkinize açık modüller aşağıda listelendi.<br>Devam etmek için birine tıklayın.</div>

            {{-- hidden token handoff (same pattern as tedariklogin) --}}
            @if (\Session::has('sms-success'))
                <input type="hidden" name="apiKey" value="{{\Session::get('sms-success')}}">
            @elseif (\Session::has('sms-firstlogin'))
                <input type="hidden" name="apiKey" value="{{\Session::get('sms-firstlogin')}}">
                <input type="hidden" name="firstLogin" value="{{\Session::get('sms-firstlogin')}}">
            @endif

            <div class="grid" id="module-grid">
                @foreach($modules as $m)
                    @php
                        $isDisabled = !empty($m['disabled']);
                        $color = $m['color'] ?? '#FF4713';
                    @endphp
                    <button type="button"
                            class="mod {{ $isDisabled ? 'disabled' : '' }}"
                            style="--mc: {{ $color }}"
                            data-route="{{ $m['route'] }}"
                            data-disabled="{{ $isDisabled ? '1' : '0' }}"
                            data-title="{{ $m['title'] }}"
                            {{ $isDisabled ? 'disabled' : '' }}>
                        <div class="mod-top">
                            <div class="mod-icon">
                                @if(($m['icon'] ?? '') === 'shield')
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 3l7 3v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
                                @elseif(($m['icon'] ?? '') === 'truck')
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M14 18V6a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v12h13z"/><path d="M14 9h5l3 3v6h-4"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
                                @elseif(($m['icon'] ?? '') === 'gavel')
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M14 13l6 6"/><path d="M16 16l4-4"/><path d="M10 14a3 3 0 1 1-4-4l8-8a3 3 0 0 1 4 4l-8 8z"/><path d="M3 21l3-3"/></svg>
                                @else
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 9l9-6 9 6v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                                @endif
                            </div>
                            <div>
                                <div class="mod-title">{{ $m['title'] }}</div>
                                <div class="mod-desc">{{ $m['desc'] ?? '' }}</div>
                            </div>
                            <div class="mod-arrow">→</div>
                        </div>
                        @if($isDisabled)
                            <span class="badge-soon">● Yakında</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="foot">
                <a href="/logout">Çıkış Yap</a>
                <span>·</span>
                <span id="user-email" style="color:#475569; font-weight:600;">{{ session('email') ?? '' }}</span>
            </div>
        </div>
    </div>

    <script type="module">
        const tokenInput = document.querySelector('input[name="apiKey"]');
        if(tokenInput && tokenInput.value.trim()){
            localStorage.setItem('token', tokenInput.value.trim());
            // also store firstLogin if present
            const first = document.querySelector('input[name="firstLogin"]');
            if(first && first.value){
                // let tedariklogin handle firstLogin redirect elsewhere, but here we also handle
                setTimeout(()=> window.location.href = 'auth/passwordreset/firstlogin', 300);
            }
        }
        document.querySelectorAll('.mod:not(.disabled)').forEach(btn=>{
            btn.addEventListener('click', ()=>{
                const route = btn.dataset.route;
                // pulse
                btn.style.transform = 'scale(0.99)';
                setTimeout(()=> window.location.href = route, 120);
            });
        });
        document.querySelectorAll('.mod.disabled').forEach(btn=>{
            btn.addEventListener('click', (e)=>{
                e.preventDefault();
                if(window.Swal){
                    Swal.fire({ icon:'info', title: btn.dataset.title, text: 'Bu modül yakında aktif olacak.', confirmButtonText: 'Tamam', confirmButtonColor: '#FF4713' });
                }
            });
        });
    </script>
</body>
</html>
