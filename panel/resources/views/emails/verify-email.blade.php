@php
    $sys = strtoupper(trim($sysCode ?? 'GDZ'));
    if (!in_array($sys, ['GDZ', 'ADM'], true)) $sys = 'GDZ';
    $content = '<p>Merhaba ' . e($name ?? 'Müşteri') . ',</p>' .
        '<p>Tedarik Yönetim Sistemi hesabınız aktif edildi. Giriş bilgilerinizle panele erişebilirsiniz.</p>';
@endphp
@include('emails.layout', [
    'sysCode' => $sys,
    'logoUrl' => \App\Services\TedarikMailHelper::logoUrl($sys),
    'title' => 'Hesap Aktivasyonu',
    'header' => 'Hesabınız Aktif',
    'intro' => 'Hesabınız etkinleştirildi. Aşağıdaki butonla giriş yapın.',
    'content' => $content,
    'ctaUrl' => $ctaUrl ?? \App\Services\TedarikMailHelper::gateway('/tedarikpanel'),
    'ctaText' => 'Giriş Yap',
    'pillText' => 'Aktivasyon',
    'pillColor' => '#15803d',
    'pillBg' => '#dcfce7',
    'subtext' => 'Bu e-postayı siz talep etmediyseniz lütfen göz ardı edin.',
    'footerText' => 'Tedarik Yönetim Sistemi tarafından gönderildi.',
])
