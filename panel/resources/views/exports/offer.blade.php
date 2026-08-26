<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Teklif PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; margin: 0; padding: 0; }
        .page { padding: 20px; font-size: 10px; line-height: 1.35; }
        .card { border: 1px solid #d1d5db; border-radius: 6px; overflow: hidden; }
        .header { background: #111827; color: white; padding: 14px 16px; }
        .header-grid { display: table; width: 100%; table-layout: fixed; }
        .header-block { display: table-cell; vertical-align: middle; }
        .brand { font-size: 13px; font-weight: 700; letter-spacing: 0.03em; }
        .brand small { font-size: 9px; color: #d1d5db; display: block; margin-top: 4px; font-weight: 400; }
        .title { font-size: 20px; text-align: center; font-weight: 700; letter-spacing: 0.08em; }
        .meta { text-align: right; font-size: 9.5px; }
        .meta div { margin-bottom: 4px; }
        .section { border-bottom: 1px solid #e5e7eb; padding: 12px 16px; }
        .section:last-child { border-bottom: none; }
        .section-title { font-size: 11px; font-weight: 700; margin-bottom: 8px; color: #111827; }
        .table { width: 100%; border-collapse: collapse; }
        .table td { vertical-align: top; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
        .table tr:last-child td { border-bottom: none; }
        .label { width: 28%; color: #374151; font-weight: 700; }
        .value { width: 72%; color: #111827; }
        .value-box { background: #f8fafc; padding: 6px 8px; border-radius: 4px; }
        .summary { background: #f3f4f6; border-radius: 4px; padding: 10px 12px; margin-top: 8px; }
        .summary div { margin-bottom: 4px; }
        .note { padding: 10px 12px; background: #f8fafc; border-radius: 4px; min-height: 58px; white-space: pre-wrap; }
        .footer { padding: 14px 16px; font-size: 9px; color: #4b5563; }
        .footer strong { color: #111827; }
    </style>
</head>
<body>
    <div class="page card">
        <div class="header">
            <div class="header-grid">
                <div class="header-block" style="width: 35%; text-align: left;">
                    <div class="brand">{{ $form['clititle'] ?? '' }}</div>
                    <div><strong>Santral:</strong> {{ $form['target_type'] ?? '' }}</div>
            <div><strong>Talep Kodu:</strong> {{ $form['request_id'] ?? '' }}</div>
                </div>
                <div class="header-block" style="width: 40%;">
                    <div class="title">TEKLİF FORMU</div>
                </div>
                <div class="header-block" style="width: 25%;" class="meta">
                    <div><strong>Belge No:</strong> {{ $document->qnid ?? '' }}</div>
                    <div><strong>Tarih:</strong> {{ $form['date'] ?? '' }}</div>
                    <div><strong>Rev. Tarihi:</strong> {{ $form['rev_date'] ?? '' }}</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Temel Bilgiler</div>
            <table class="table">
                <tr>
                    <td class="label">Alıcı</td>
                    <td class="value"><div class="value-box">{{ $form['buyer'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Satıcı</td>
                    <td class="value"><div class="value-box">{{ $form['seller'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Teklif Durumu</td>
                    <td class="value"><div class="value-box">{{ $latestStatus ?: ($document->op_key ?? '') }}</div></td>
                </tr>
                <tr>
                    <td class="label">Sipariş Kapsamı</td>
                    <td class="value"><div class="value-box">{{ $form['order_radius'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Sözleşme Tarihleri</td>
                    <td class="value"><div class="value-box">{{ $form['contract_start_date'] ?? '' }} - {{ $form['contract_end_date'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Yükleme / Boşaltma</td>
                    <td class="value"><div class="value-box">{{ $form['load_area'] ?? '' }} / {{ $form['unload_area'] ?? '' }}</div></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Kömür Özellikleri</div>
            <table class="table">
                <tr>
                    <td class="label">Cinsi</td>
                    <td class="value"><div class="value-box">{{ $form['coal_type'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Kalori</td>
                    <td class="value"><div class="value-box">{{ $form['calory'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Nem</td>
                    <td class="value"><div class="value-box">{{ $form['humidity'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Kül</td>
                    <td class="value"><div class="value-box">{{ $form['ash_content'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Kükürt</td>
                    <td class="value"><div class="value-box">{{ $form['sulfur'] ?? '' }}</div></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Fiyatlandırma</div>
            <table class="table">
                <tr>
                    <td class="label">Birim Fiyat</td>
                    <td class="value"><div class="value-box">{{ $form['unit_price'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Nakliye</td>
                    <td class="value"><div class="value-box">{{ $form['shipping_included'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Akaryakıt Etkisi</td>
                    <td class="value"><div class="value-box">{{ $form['fuel_price_impact'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">(Tİ-ÜFE + TÜFE)/2</td>
                    <td class="value"><div class="value-box">{{ $form['tiufe_price_impact'] ?? '' }}</div></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Prim / Penalite</div>
            <table class="table">
                <tr>
                    <td class="label">Dahil Aralık</td>
                    <td class="value"><div class="value-box">{{ $form['prime_condition_is'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Alt Sınır</td>
                    <td class="value"><div class="value-box">{{ $form['prime_condition_is_bellow'] ?? '' }}</div></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Teslim Bilgileri</div>
            <table class="table">
                <tr>
                    <td class="label">Miktar</td>
                    <td class="value"><div class="value-box">{{ $form['amount'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Hakediş Dönemleri</td>
                    <td class="value"><div class="value-box">{{ $form['payment_periods'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Ödeme Vadesi</td>
                    <td class="value"><div class="value-box">{{ $form['payment_due'] ?? '' }}</div></td>
                </tr>
                <tr>
                    <td class="label">Sevkiyat Başlangıcı</td>
                    <td class="value"><div class="value-box">{{ $form['start_date'] ?? '' }}</div></td>
                </tr>
            </table>
        </div>

        

        <div class="section">
            <div class="section-title">Ek Açıklama</div>
            <div class="note">{{ $form['desc'] ?? '' }}</div>
        </div>

        <div class="section">
            <div class="section-title">Durum Güncellemeleri</div>
            <table class="table">
                <tr style="background: #f8fafc; font-weight: 700;">
                    <td style="width:25%;">Durum</td>
                    <td style="width:20%;">Tarih</td>
                    <td style="width:20%;">Kişi</td>
                    <td style="width:35%;">Açıklama</td>
                </tr>
                @forelse($statusHistory ?? [] as $s)
                    <tr>
                        <td>{{ is_array($s) ? ($s['op_title'] ?? '') : ($s->op_title ?? '') }}</td>
                        <td>
                            {{ is_array($s)
                                ? (!empty($s['created_at']) ? \Carbon\Carbon::parse($s['created_at'])->format('d.m.Y') : '')
                                : (!empty($s->created_at) ? \Carbon\Carbon::parse($s->created_at)->format('d.m.Y') : '')
                            }}
                        </td>
                        <td>{{ is_array($s) ? ($s['name'] ?? '') : ($s->name ?? '') }}</td>
                        <td>{{ is_array($s) ? ($s['note'] ?? '') : ($s->note ?? '') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Güncelleme yok</td>
                    </tr>
                @endforelse
            </table>
        </div>

    </div>
</body>
</html>
