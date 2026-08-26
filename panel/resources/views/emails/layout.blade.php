<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Kömür Tedarik Sistemi' }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2ff;color:#111827;font-family:Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef2ff;padding:20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background:#ffffff;border-radius:32px;overflow:hidden;box-shadow:0 28px 70px rgba(15,23,42,0.12);">
                    <tr>
                        <td style="padding:28px 32px;text-align:center;">
                            @php
                                /*$sysCode = $sysCode ?? $sys_code ?? '';
                                $logoSrc = asset('coaltheme/'.$sysCode.'MAIL.jpg');
                                $logoPathJpg = public_path('coaltheme/'.$sysCode.'MAIL.jpg');
                                $logoPathPng = public_path('coaltheme/'.$sysCode.'MAIL.png');
                                $logoPath = null;
                                if (file_exists($logoPathJpg) && is_readable($logoPathJpg)) {
                                    $logoPath = $logoPathJpg;
                                } elseif (file_exists($logoPathPng) && is_readable($logoPathPng)) {
                                    $logoPath = $logoPathPng;
                                }
                                if ($logoPath) {
                                    $mime = pathinfo($logoPath, PATHINFO_EXTENSION) === 'png' ? 'image/png' : 'image/jpeg';
                                    $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoPath));
                                }*/

                                // this layout is also reached through @include from other mail
                                // views, which do not always carry sysCode — never hard-require it
                                $sysCode = $sysCode ?? $sys_code ?? '';
                                $logoSrc = $sysCode == 'CATES' ? 'https://www.cates.com.tr/storage/logo/cates.jpg' : 'https://www.cates.com.tr/storage/logo/yatagan.jpg';


                            @endphp
                            <img src="{{ $logoSrc }}" alt="Kömür Tedarik Sistemi" width="140" style="display:block;margin:0 auto 18px;max-width:100%;height:auto;" />
                            <h1 style="margin:0;font-size:24px;line-height:1.3;">{{ $header ?? $title ?? 'Kömür Tedarik Sistemi' }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 32px 24px;">
                            @if(!empty($intro))
                                <p style="margin:0 0 24px;color:#475569;font-size:15px;line-height:1.8;">{{ $intro }}</p>
                            @endif
                            <div style="margin:0 0 24px;color:#475569;font-size:15px;line-height:1.8;">
                                {!! $content ?? '' !!}
                            </div>
                            @if(!empty($ctaUrl))
                                <table align="center" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 32px;">
                                    <tr>
                                        <td style="border-radius:999px;background:#2c4cf1;text-align:center;">
                                            <a href="{{ $ctaUrl }}" target="_blank" style="display:inline-block;padding:14px 34px;font-size:15px;color:#ffffff;text-decoration:none;font-weight:600;border-radius:999px;">{{ $ctaText ?? 'Devam Et' }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                            <p style="margin:0;color:#64748b;font-size:14px;line-height:1.7;">{{ $subtext ?? 'Bu e-posta açıklama amaçlıdır. Eğer bu e-postayı siz talep etmediyseniz lütfen bu mesajı göz ardı ediniz.' }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc;padding:24px 32px;text-align:center;color:#64748b;font-size:13px;line-height:1.7;">
                            <p style="margin:0;">{{ $footerText ?? 'Kömür Tedarik Sistemi tarafından gönderildi. ' }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
