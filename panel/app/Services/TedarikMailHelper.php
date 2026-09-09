<?php

namespace App\Services;

/**
 * Central helper for tedarik-panel styled notification emails.
 * - Logo from APP_URL (config app.url), never hardcoded external host
 * - Deep links to tedarik panel via /tedarik?next=... gateway (login required, then redirect)
 * - Turkish copy only, table-based HTML safe for mail clients
 */
class TedarikMailHelper
{
    public static function appBase(): string
    {
        return rtrim(config('app.url', env('APP_URL', 'http://localhost:8000')), '/');
    }

    /** Single BUKRS→system mapping. Raw '4000'/'5000' codes included. */
    public static function normalizeSys($raw): string
    {
        $b = strtoupper(trim((string) $raw));
        if ($b === '') return 'GDZ';
        if (in_array($b, ['ADM', '5000', 'A5000'], true) || str_contains($b, 'ADM')) return 'ADM';
        if (in_array($b, ['BOTH', 'HER_IKISI', 'GDZ,ADM', 'GDZ/ADM'], true)) return 'BOTH';
        return 'GDZ';
    }

    public static function sysCode(array $payload): string
    {
        $raw = $payload['sys_code'] ?? $payload['bukrs'] ?? $payload['BUKRS'] ?? $payload['order_sys_code'] ?? '';
        return self::normalizeSys($raw);
    }

    /**
     * Mail logo files: PNGs rasterized from the real vector marks
     * (GDZ.svg local, adm-logo.svg from yts.admelektrik.com.tr).
     * SVG itself is NOT inbox-safe (Gmail/Outlook strip it) — PNG bytes go out.
     */
    public static function logoMime(): string
    {
        return 'image/png';
    }

    /** Raw bytes of the optimized mail logo (≈13KB). Null when missing. */
    public static function logoBytes(string $sysCode): ?string
    {
        static $cache = [];
        $sysCode = self::normalizeSys($sysCode);
        if ($sysCode === 'BOTH' || !in_array($sysCode, ['GDZ', 'ADM'], true)) $sysCode = 'GDZ';
        if (array_key_exists($sysCode, $cache)) return $cache[$sysCode];

        $file = public_path('coaltheme/' . ($sysCode === 'ADM' ? 'mail-adm.png' : 'mail-gdz.png'));
        $bin = (is_readable($file) && ($raw = @file_get_contents($file)) !== false && $raw !== '') ? $raw : null;
        return $cache[$sysCode] = $bin;
    }

    /**
     * Email-safe logo: base64 data URI of the optimized mail-*.png (≈13KB).
     * Used for stored bodies / browser previews (data URIs render there).
     * Gmail strips data URIs — MailService re-embeds these same bytes as a
     * CID attachment at send time, so inboxes still see the logo.
     */
    public static function logoUrl(string $sysCode): string
    {
        $bin = self::logoBytes($sysCode);
        if ($bin !== null) return 'data:' . self::logoMime() . ';base64,' . base64_encode($bin);
        // fallback: absolute APP_URL (works only when publicly reachable)
        $sysCode = self::normalizeSys($sysCode);
        if (!in_array($sysCode, ['GDZ', 'ADM'], true)) $sysCode = 'GDZ';
        $name = $sysCode === 'ADM' ? 'adm.jpg' : 'gdz.jpg';
        return self::appBase() . '/coaltheme/' . $name;
    }

    /** Gateway link: public /tedarik login page preserves ?next, then JS redirects after 2FA token. */
    public static function gateway(string $tedarikPath): string
    {
        if (!str_starts_with($tedarikPath, '/')) $tedarikPath = '/' . $tedarikPath;
        return self::appBase() . '/tedarik?next=' . urlencode($tedarikPath);
    }

    public static function orderUrl(?string $orderQnid): ?string
    {
        if (empty($orderQnid)) return null;
        return self::gateway('/tedarikpanel/orders/form/' . $orderQnid);
    }

    public static function fileUrl(?string $fileQnid): ?string
    {
        if (empty($fileQnid)) return null;
        return self::gateway('/tedarikpanel/documents/' . $fileQnid);
    }

    /** Build label/value detail table rows (already escaped by caller via e()). */
    public static function detailTable(array $rows): string
    {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #e8e8ea;border-radius:10px;overflow:hidden;border-collapse:separate;border-spacing:0;">';
        $i = 0;
        foreach ($rows as $row) {
            [$label, $value] = $row;
            $bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
            $html .= '<tr>'
                . '<td style="background:' . $bg . ';padding:10px 14px;font-size:13px;color:#64748b;width:38%;border-bottom:1px solid #eef0f2;">' . $label . '</td>'
                . '<td style="background:' . $bg . ';padding:10px 14px;font-size:13.5px;color:#0f172a;font-weight:600;border-bottom:1px solid #eef0f2;">' . $value . '</td>'
                . '</tr>';
            $i++;
        }
        $html .= '</table>';
        return $html;
    }

    public static function noteBox(?string $note): string
    {
        $note = trim((string) ($note ?? ''));
        if ($note === '' || $note === '-') return '';
        return '<div style="margin-top:14px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;font-size:13.5px;line-height:1.6;color:#92400e;">'
            . '<strong>Not:</strong> ' . e($note)
            . '</div>';
    }

    public static function metaFor(string $type): array
    {
        return match ($type) {
            'tedarikOrderImported' => ['pill' => 'SAP Üzerinden Geldi', 'color' => '#154B91', 'bg' => '#eff6ff', 'intro' => 'Yeni sipariş SAP üzerinden sisteme eklendi. Kontrol edip tedarik sürecini başlatın.'],
            'tedarikOrderSent'     => ['pill' => 'Onaya Gönderildi', 'color' => '#c2410c', 'bg' => '#fff7ed', 'intro' => 'Tedarikçi siparişi onaya gönderdi. Dosyaları inceleyin.'],
            'tedarikFileWaiting'   => ['pill' => 'İnceleme Bekliyor', 'color' => '#b45309', 'bg' => '#fef3c7', 'intro' => 'Sipariş ile birlikte inceleme bekleyen dosyalar eklendi.'],
            'tedarikFileApproved'  => ['pill' => 'Dosya Onaylandı', 'color' => '#15803d', 'bg' => '#dcfce7', 'intro' => 'Sipariş dosyası onaylandı.'],
            'tedarikFileRejected'  => ['pill' => 'Yeniden Talep', 'color' => '#b91c1c', 'bg' => '#fee2e2', 'intro' => 'Sipariş dosyası reddedildi — tedarikçiden yeniden talep edildi.'],
            'tedarikOrderApproved' => ['pill' => 'Kalite Onayı', 'color' => '#15803d', 'bg' => '#dcfce7', 'intro' => 'Sipariş kalite onayı verildi ve kapatıldı.'],
            'tedarikOrderRejected' => ['pill' => 'Sipariş Reddedildi', 'color' => '#b91c1c', 'bg' => '#fee2e2', 'intro' => 'Sipariş reddedildi. Detayı inceleyin.'],
            default                => ['pill' => 'Bilgilendirme', 'color' => '#475569', 'bg' => '#f1f5f9', 'intro' => null],
        };
    }
}
