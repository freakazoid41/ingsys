<!doctype html>
<html lang="tr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sevk Edilecek Malzemenin Cinsi Ve Miktar&#305;</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; margin: 0; padding: 0; }
        .page { padding: 40px 30px; font-size: 11px; line-height: 1.4; }
        .title { text-align: center; font-size: 16px; font-weight: 700; margin-bottom: 30px; letter-spacing: 0.04em; text-decoration: underline; text-underline-offset: 6px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .header-table td { border: 1.5px solid #000; padding: 6px 10px; font-size: 12px; vertical-align: middle; }
        .header-table .label { font-weight: 700; width: 22%; text-align: left; }
        .header-table .divider { width: 3%; text-align: center; padding: 6px 4px; }
        .header-table .value { width: 75%; text-align: center; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { border: 2px solid #000; padding: 7px 6px; font-size: 9.5px; font-weight: 700; text-align: center; background: #fff; }
        .items-table td { border: 2px solid #000; padding: 6px; font-size: 10px; vertical-align: middle; }
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
        <div class="title">SEVK ED&#304;LECEK MALZEMEN&#304;N C&#304;NS&#304; VE M&#304;KTARI</div>

        <table class="header-table">
            <tr>
                <td class="label">&#350;irket</td>
                <td class="divider">:</td>
                <td class="value">{{ $company ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">&#304;malat&#231;&#305; Firma</td>
                <td class="divider">:</td>
                <td class="value">{{ $imalatci_firma_adi ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">&#304;hale NO</td>
                <td class="divider">:</td>
                <td class="value">{{ $buying_no ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Sipari&#351; NO</td>
                <td class="divider">:</td>
                <td class="value">{{ $order_no ?? '' }}</td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:14%;">Malzeme Kodu</th>
                    <th style="width:34%;">Tespit Edilen Malzeme Cinsi</th>
                    <th style="width:7%;">Birim</th>
                    <th style="width:10%;">Miktar</th>
                    <th style="width:18%;">Seri / Parti Numaras&#305;</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $item['prod_code'] ?? '' }}</td>
                        <td>{{ $item['title'] ?? '' }}</td>
                        <td class="center">{{ $item['unit'] ?? '' }}</td>
                        <td class="center">{{ $item['quantity'] ?? '' }}</td>
                        <td class="center">{{ $item['serial_no'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;">Kalem bulunamad&#305;</td>
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
