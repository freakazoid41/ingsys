<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Tedarik Yönetim Sistemi' }}</title>
</head>
@if(!empty($preheader))
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
@endif
<body style="margin:0;padding:0;background:#f2f2f3;color:#111827;font-family:Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f2f2f3;padding:20px 0;">
        <tr>
            <td align="center">
                <table width="620" cellpadding="0" cellspacing="0" role="presentation" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,0.07);border:1px solid rgba(0,0,0,0.04);">
                    <tr>
                        <td style="height:4px;line-height:4px;font-size:4px;background:linear-gradient(90deg,#FF4713,#fb923c);background-color:#FF4713;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:26px 30px 6px;text-align:left;">
                            @php
                                // Logo is embedded as base64 (TedarikMailHelper::logoUrl) so it
                                // renders in every inbox — remote URLs get blocked, localhost
                                // is unroutable from mail clients.
                                $sysCode = \App\Services\TedarikMailHelper::normalizeSys($sysCode ?? $sys_code ?? 'GDZ');
                                if (!in_array($sysCode, ['GDZ', 'ADM'], true)) $sysCode = 'GDZ';
                                $logoSrc = $logoUrl ?? \App\Services\TedarikMailHelper::logoUrl($sysCode);
                                $accent = $accent ?? '#FF4713';
                                $pillText = $pillText ?? null;
                                $pillColor = $pillColor ?? '#c2410c';
                                $pillBg = $pillBg ?? '#fff7ed';
                            @endphp
                            <div style="text-align:center;">
                                <img src="{{ $logoSrc }}" alt="Tedarik Yönetim Sistemi" width="160" style="display:inline-block;max-width:160px;height:auto;" />
                            </div>
                            <h1 style="margin:18px 0 0;font-size:21px;line-height:1.35;color:#0f172a;">{{ $header ?? $title ?? 'Tedarik Yönetim Sistemi' }}</h1>
                            @if(!empty($pillText))
                                <div style="margin-top:12px;">
                                    <span style="display:inline-block;background:{{ $pillBg }};color:{{ $pillColor }};border:1px solid {{ $pillColor }}33;border-radius:999px;padding:6px 14px;font-size:12.5px;font-weight:700;">{{ $pillText }}</span>
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 30px 24px;">
                            @if(!empty($intro))
                                <p style="margin:0 0 16px;color:#475569;font-size:14.5px;line-height:1.7;">{{ $intro }}</p>
                            @endif
                            <div style="margin:0 0 18px;color:#475569;font-size:14.5px;line-height:1.7;">
                                {!! $content ?? '' !!}
                            </div>
                            @if(!empty($ctaUrl))
                                <table cellpadding="0" cellspacing="0" role="presentation" style="margin:6px 0 18px;">
                                    <tr>
                                        <td style="border-radius:10px;background:#FF4713;text-align:center;">
                                            <a href="{{ $ctaUrl }}" target="_blank" style="display:inline-block;padding:13px 28px;font-size:14.5px;color:#ffffff;text-decoration:none;font-weight:700;border-radius:10px;">{{ $ctaText ?? 'Tedarik Panelinde Aç' }}</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 14px;color:#94a3b8;font-size:12.5px;line-height:1.6;">Bağlantı giriş gerektirir. Giriş yaptıktan sonra ilgili kayıt otomatik açılır.</p>
                            @endif
                            <p style="margin:0;color:#64748b;font-size:13px;line-height:1.7;">{{ $subtext ?? 'Bu e-posta bilgilendirme amaçlıdır. İlgili bir işleminiz yoksa bu mesajı göz ardı edebilirsiniz.' }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc;padding:18px 30px;text-align:center;color:#64748b;font-size:12.5px;line-height:1.7;border-top:1px solid #eef0f2;">
                            <p style="margin:0;">{{ $footerText ?? 'Tedarik Yönetim Sistemi tarafından gönderildi.' }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
