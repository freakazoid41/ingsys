<!doctype html>
<html lang="tr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Malzeme Kabul Formu</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; margin: 0; padding: 0; }
        .page { padding: 40px 30px; font-size: 11px; line-height: 1.4; }
        .title { text-align: center; font-size: 18px; font-weight: 700; margin-bottom: 40px; letter-spacing: 0.04em; text-decoration: underline; text-underline-offset: 6px; }
        .header-left { margin-bottom: 8px; }
        .header-left div { margin-bottom: 4px; font-size: 12px; }
        .header-right { text-align: right; margin-top: 20px; margin-bottom: 16px; font-size: 12px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { border: 2px solid #000; padding: 7px 8px; font-size: 10px; font-weight: 700; text-align: center; background: #fff; }
        .items-table td { border: 2px solid #000; padding: 7px 8px; font-size: 10.5px; vertical-align: middle; }
        .items-table td.center { text-align: center; }
        .section-title { font-size: 13px; font-weight: 700; margin: 20px 0 10px; }
        .note-box { min-height: 30px; white-space: pre-wrap; margin-bottom: 30px; font-size: 11px; }
        .signature-grid { width: 100%; border-collapse: collapse; margin-top: 40px; }
        .signature-grid td { border: 2px solid #000; padding: 14px 20px; text-align: center; vertical-align: middle; font-weight: 700; font-size: 13px; }
    </style>
</head>
<body>
    @php mb_internal_encoding('UTF-8'); @endphp
    <div class="page">
        <div class="title">MALZEME KABUL FORMU</div>

        <div class="header-left">
            <div>Al&#231;m No : {{ $buying_no ?? '' }}</div>
            <div>Sipari&#351; No : {{ $order_no ?? '' }}</div>
        </div>

        <div class="header-right">
            Sipari&#351; Tarihi : {{ $created_at ?? '' }}
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:4%;">S.No</th>
                    <th style="width:16%;">Malzeme Kodu</th>
                    <th style="width:32%;">Malzeme Ad&#305;</th>
                    <th style="width:7%;">Birim</th>
                    <th style="width:18%;">Sipari&#351; Miktar&#305;</th>
                    <th style="width:23%;">Kabul Yap&#305;lacak Miktar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $index => $item)
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td>{{ $item['prod_code'] ?? '' }}</td>
                        <td>{{ $item['title'] ?? '' }}</td>
                        <td class="center">{{ $item['unit'] ?? '' }}</td>
                        <td class="center">{{ $item['quantity'] ?? '' }}</td>
                        <td class="center">{{ $item['accept_quantity'] ?? $item['quantity'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;">Kalem bulunamad&#305;</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="section-title">A&#231;&#305;klamalar :</div>
        <div class="note-box">{{ $order_desc ?? '-' }}</div>

        <table class="signature-grid">
            <tr>
                <td style="width:50%;">&#304;malat&#231;&#305; Firma</td>
                <td style="width:50%;">Onaylayan</td>
            </tr>
            <tr>
                <td style="height:80px;vertical-align:middle;text-align:center;font-weight:600;font-size:12px;">{{ $imalatci_firma_adi ?? '' }}</td>
                <td style="height:80px;"></td>
            </tr>
        </table>
    </div>
</body>
</html>
