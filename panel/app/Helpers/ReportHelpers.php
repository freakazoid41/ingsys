<?php 
if(!function_exists('getMonths')){
    function getMonths(){
        return ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    }
}
if(!function_exists('getLocation')){
    function getLocation($cityCode = '',$areaCode = ''){
        $list = [
            '35' => [
                'title' => 'İzmir',
                'subs'  => [
                    '2' => [
                        'title' => 'Bayındır'
                    ],
                    '3' => [
                        'title' => 'Bergama'
                    ],
                    '4' => [
                        'title' => 'Bornova'
                    ],
                    '5' => [
                        'title' => 'Çeşme'
                    ],
                    '6' => [
                        'title' => 'Dikili'
                    ],
                    '7' => [
                        'title' => 'Foça'
                    ],
                    '8' => [
                        'title' => 'Karaburun'
                    ],
                    '9' => [
                        'title' => 'Karşıyaka'
                    ],
                    '10' => [
                        'title' => 'Kemalpaşa'
                    ],
                    '11' => [
                        'title' => 'Kınık'
                    ],
                    '12' => [
                        'title' => 'Kiraz'
                    ],
                    '13' => [
                        'title' => 'Menemen'
                    ],
                    '14' => [
                        'title' => 'Ödemiş'
                    ],
                    '15' => [
                        'title' => 'Seferihisar'
                    ],
                    '16' => [
                        'title' => 'Selçuk'
                    ],
                    '17' => [
                        'title' => 'Tire'
                    ],
                    '18' => [
                        'title' => 'Torbalı'
                    ],
                    '19' => [
                        'title' => 'Urla'
                    ],
                    '20' => [
                        'title' => 'Aliağa'
                    ],
                    '21' => [
                        'title' => 'Güzelbahçe'
                    ],
                    '22' => [
                        'title' => 'Menderes'
                    ],
                    '23' => [
                        'title' => 'Konak'
                    ],
                    '24' => [
                        'title' => 'Beydağ'
                    ],
                    '29' => [
                        'title' => 'Narlıdere'
                    ],
                    '30' => [
                        'title' => 'Bayraklı'
                    ],
                ]
            ],
            '45' => [
                'title' => 'Manisa',
                'subs'  => [
                    '2' => [
                        'title' => 'Akhisar'
                    ],
                    '3' => [
                        'title' => 'Alaşehir'
                    ],
                    '4' => [
                        'title' => 'Demirci'
                    ],
                    '5' => [
                        'title' => 'Gördes'
                    ],
                    '6' => [
                        'title' => 'Kırkağaç'
                    ],
                    '7' => [
                        'title' => 'Kula'
                    ],
                    '8' => [
                        'title' => 'Salihli'
                    ],
                    '9' => [
                        'title' => 'Sarıgöl'
                    ],
                    '10' => [
                        'title' => 'Saruhanlı'
                    ],
                    '11' => [
                        'title' => 'Selendi'
                    ],
                    '12' => [
                        'title' => 'Soma'
                    ],
                    '13' => [
                        'title' => 'Turgutlu'
                    ],
                    '14' => [
                        'title' => 'Ahmetli'
                    ],
                    '15' => [
                        'title' => 'Gölmarmara'
                    ],
                    '16' => [
                        'title' => 'Köprübaşı'
                    ],
                    '17' => [
                        'title' => 'Şehzadeler'
                    ],
                    '18' => [
                        'title' => 'Yunusemre'
                    ]
                ]
            ],
            '48' => [
                'title' => 'Muğla',
                'subs'  => [
                    '13' => [
                        'title' => 'Muğla Bölge Müdürlüğü'
                    ],
                    '10' => [
                        'title' => 'Dalaman'
                    ],
                    '4' => [
                        'title' => 'Fethiye'
                    ],
                    '12' => [
                        'title' => 'Kavaklıdere'
                    ],
                    '5' => [
                        'title' => 'Köyceğiz'
                    ],
                    '3' => [
                        'title' => 'Datça'
                    ],
                    '6' => [
                        'title' => 'Marmaris'
                    ],
                    '9' => [
                        'title' => 'Yatağan'
                    ],
                    '11' => [
                        'title' => 'Ortaca'
                    ],
                    '14' => [
                        'title' => 'Seydikemer'
                    ],
                    '8' => [
                        'title' => 'Ula'
                    ],
                    '2' => [
                        'title' => 'Bodrum Bölge'
                    ],
                    '7' => [
                        'title' => 'Milas Bölge'
                    ],
                ]
            ],
            '9' => [
                'title' => 'Aydın',
                'subs'  => [
                    '1' => [
                        'title' => 'Aydın Bölge Müdürlüğü'
                    ],
                    '2' => [
                        'title' => 'Bozdoğan'
                    ],
                    '13' => [
                        'title' => 'Buharkent'
                    ],
                    '3' => [
                        'title' => 'Çine'
                    ],
                    '17' => [
                        'title' => 'Didim'
                    ],
                    '4' => [
                        'title' => 'Germencik'
                    ],
                    '5' => [
                        'title' => 'Karacasu'
                    ],
                    '15' => [
                        'title' => 'Karpuzlu'
                    ],
                    '6' => [
                        'title' => 'Koçarlı'
                    ],
                    '16' => [
                        'title' => 'Köşk'
                    ],
                    '7' => [
                        'title' => 'Kuşadası'
                    ],
                    '14' => [
                        'title' => 'İncirliova'
                    ],
                    '8' => [
                        'title' => 'Kuyucak'
                    ],
                    '9' => [
                        'title' => 'Nazilli'
                    ],
                    '10' => [
                        'title' => 'Söke'
                    ],
                    '11' => [
                        'title' => 'Sultanhisar'
                    ],
                    '12' => [
                        'title' => 'Yenipazar'
                    ],
                ]
            ],
            '20' => [
                'title' => 'Denizli',
                'subs'  => [
                    '21' => [
                        'title' => 'Denizli Bölge Müsürlüğü'
                    ],
                    '2' => [
                        'title' => 'Acıpayam'
                    ],
                    '11' => [
                        'title' => 'Tavas'
                    ],
                    '12' => [
                        'title' => 'Babadağ'
                    ],
                    '17' => [
                        'title' => 'Baklan'
                    ],
                    '13' => [
                        'title' => 'Bekilli'
                    ],
                    '18' => [
                        'title' => 'Beyağaç'
                    ],
                    '19' => [
                        'title' => 'Bozkurt'
                    ],
                    '5' => [
                        'title' => 'Çameli'
                    ],
                    '4' => [
                        'title' => 'Çal'
                    ],
                    '6' => [
                        'title' => 'Çardak'
                    ],
                    '7' => [
                        'title' => 'Çivril'
                    ],
                    '8' => [
                        'title' => 'Güney'
                    ],
                    '9' => [
                        'title' => 'Kale'
                    ],
                    '20' => [
                        'title' => 'Pamukkale'
                    ],
                    '3' => [
                        'title' => 'Buldan'
                    ],
                    '10' => [
                        'title' => 'Sarayköy'
                    ],
                    '15' => [
                        'title' => 'Serinhisar'
                    ],
                ]
            ],
        ];

        $return = [];

        if($cityCode !== '' && $areaCode !== '') $return[] = $list[$cityCode]['title'];
        if($cityCode !== '') $return[] = $list[$cityCode]['subs'][$areaCode]['title'];
       


        return $return;
    }
}
if(!function_exists('getMissingDaysD')){
    function getMissingDaysD(){
        return [
            '01' => 'İstirahat',
            '02' => 'Ücretsiz İzin',
            '03' => 'Disiplin Cezası',
            '04' => 'Gözaltına Alınma',
            '05' => 'Tutukluluk',
            '06' => 'Kısmi İstihdam',
            '07' => 'Puantaj Kayıtları',
            '08' => 'Grev',
            '09' => 'Lokavt',
            '10' => 'Genel Hayatı etkileyen Olaylar',
            '11' => 'Doğal Afet',
            '12' => 'Birden Fazla',
            '13' => 'Diğer Nedenler',
            '15' => 'Devamsızlık',
            '16' => 'Fesih Tarihinde Çalışmamış',
            '17' => 'Ev Hizmetlerinde 30 Günden Az Çalışma',
            '18' => 'Kısa Çalışma Ödeneği',
            '19' => 'Ücretsiz Doğum İzni',
            '20' => 'Ücretsiz Yol İzni',
            '21' => 'Diğer Ücretsiz İzin',
            '22' => '5434 SK. ek 76, GM 192',
            '23' => 'Yarım Çalışma Ödeneği',
            '24' => 'Yarım Çalışma Ödeneği ve Diğer Nedenler',
            '25' => 'Diğer Belge / Kanun Türlerinden Gün Tamamlama',
            '26' => 'Kısmi İstihdamla İzin Verilen Yabancı Uyruklu Sigortalı',
            '27' => 'Kısa Çalışma Ödeneği ve Diğer Nedenler',
            '28' => 'Pandemi Ücretsiz İzin (4857 Geç. 10 Md.)',
            '29' => 'Pandemi Ücretsiz İzin (4857 Geç. 10 Md.) ve Diğer',
        ];
    }
}