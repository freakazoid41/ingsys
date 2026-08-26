<?php
/**
 * docs/*.md → PDF dönüştürücü (geçici yardımcı — docs build aracı).
 * Kullanım: php docs/build_pdf.php
 * league/commonmark (GFM) + mpdf ile Türkçe uyumlu PDF üretir.
 */
require __DIR__ . '/../vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Mpdf\Mpdf;

$jobs = [
    [
        'src'   => __DIR__ . '/TEKNIK_DOKUMANTASYON.md',
        'out'   => __DIR__ . '/../TEKNIK_DOKUMANTASYON.pdf',
        'title' => 'KomurTedarik — Teknik Dokümantasyon',
    ],
    [
        'src'   => __DIR__ . '/KULLANICI_DOKUMANTASYONU.md',
        'out'   => __DIR__ . '/../KULLANICI_DOKUMANTASYONU.pdf',
        'title' => 'KomurTedarik — Kullanıcı Dokümantasyonu',
    ],
];

$css = <<<'CSS'
body { font-family: dejavusans; font-size: 10pt; line-height: 1.45; color: #1a1a1a; }
h1 { font-size: 20pt; color: #0f3057; border-bottom: 2px solid #0f3057; padding-bottom: 6px; }
h2 { font-size: 14pt; color: #0f3057; margin-top: 22px; border-bottom: 1px solid #ccd; padding-bottom: 3px; page-break-after: avoid; }
h3 { font-size: 11.5pt; color: #234; margin-top: 16px; page-break-after: avoid; }
h4 { font-size: 10.5pt; color: #345; page-break-after: avoid; }
code { font-family: dejavusansmono; font-size: 8.5pt; background: #f2f3f5; padding: 1px 3px; }
pre { background: #f6f7f9; border: 1px solid #dde; padding: 8px; font-size: 8.5pt; white-space: pre-wrap; }
pre code { background: none; padding: 0; }
table { border-collapse: collapse; width: 100%; margin: 8px 0; font-size: 8.8pt; }
th, td { border: 1px solid #bbc; padding: 4px 6px; vertical-align: top; text-align: left; }
th { background: #0f3057; color: #fff; }
tr:nth-child(even) td { background: #f4f6f8; }
blockquote { border-left: 3px solid #d9534f; margin: 8px 0; padding: 4px 12px; background: #fdf6f5; color: #444; }
ul, ol { margin: 6px 0 6px 18px; }
li { margin: 2px 0; }
hr { border: none; border-top: 1px solid #ccc; margin: 14px 0; }
strong { color: #111; }
CSS;

foreach ($jobs as $job) {
    $md = file_get_contents($job['src']);
    if ($md === false) { fwrite(STDERR, "OKUNAMADI: {$job['src']}\n"); exit(1); }

    // mpdf dejavusans emoji içermez — metin karşılıklarıyla değiştir
    $md = strtr($md, [
        "\xF0\x9F\x94\xB4" => '(KRİTİK)',   // 🔴
        "\xF0\x9F\x9F\xA0" => '(YÜKSEK)',   // 🟠
        "\xF0\x9F\x9F\xA1" => '(ORTA)',     // 🟡
        "\xE2\x9A\xAA"       => '(BİLGİ)',   // ⚪
        "\xE2\x9C\x85"       => '[DOĞRULANDI]', // ✅
        "\xF0\x9F\x93\x9D" => '[RAPOR]',    // 📝
        "\xE2\x9A\xA0"       => '[!]',       // ⚠
    ]);

    $converter = new GithubFlavoredMarkdownConverter([
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);
    $body = (string) $converter->convert($md);

    $html = '<html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>'
          . $body . '</body></html>';

    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 16,
        'margin_right' => 16,
        'margin_top' => 20,
        'margin_bottom' => 18,
        'default_font' => 'dejavusans',
    ]);
    $mpdf->SetTitle($job['title']);
    $mpdf->SetAuthor('KomurTedarik Dokümantasyon');
    $mpdf->SetHTMLHeader('<div style="text-align:right;font-size:8pt;color:#888;">' . $job['title'] . '</div>');
    $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:8pt;color:#888;">Sayfa {PAGENO} / {nbpg} — 2026-08-01</div>');
    $mpdf->WriteHTML($html);
    $mpdf->Output($job['out'], \Mpdf\Output\Destination::FILE);

    echo "OK: {$job['out']} (" . number_format(filesize($job['out'])) . " B)\n";
}
