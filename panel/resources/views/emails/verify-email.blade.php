@php
    $content = '<p>Merhaba ,</p>' .
        '<p>Kömür Tedarik Sistemi hesap aktivasyonunuz tamamlandı. Giriş bilgilerinizi kullanarak sisteme erişim sağlayabilirsiniz.</p>';
@endphp
@include('emails.layout', [
    'sysCode' => $sysCode ?? '',
    'title' => 'Hesap Aktivasyonu',
    'header' => 'Hesap Aktivasyonu',
    'intro' => 'Hesabınızı etkinleştirmek için aşağıdaki butona tıklayınız.',
    'content' => $content,
    'ctaUrl' => $ctaUrl ?? config('app.url'),
    'ctaText' => 'Giriş Yap',
    'subtext' => 'Eğer bu e-postayı siz talep etmediyseniz, lütfen bu mesajı göz ardı ediniz.',
    'footerText' => 'Kömür Tedarik Portalı tarafından gönderildi.',
])
