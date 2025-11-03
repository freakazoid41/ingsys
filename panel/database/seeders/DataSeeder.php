<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Providers\DocumentServiceProvider;

class DataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedFacilities();
    }

    private function seedFacilities(){
        $facilities = [
            [
                'name' => 'Toros HES',
                'uuid' => '50ea751a-8f72-4a32-9830-3f9e9b4c381d',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/toros-hes/50ea751a-8f72-4a32-9830-3f9e9b4c381d',
                'contact' => [
                    [
                        'name'  => 'Mesut Pehlivan',
                        'title' => 'İşletme Müdürü',
                        'email' => 'mesut.pehlivan@aydemenerji.com.tr'
                    ],[
                        'name'  => 'İsmail Dalar',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'ismail.dalar@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Eyüp Ayhan Işık',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'eyupayhan.isik@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Osman Karakoş',
                        'title' => 'İdari İşler Uzman Yardımcısı',
                        'email' => 'osman.karakos@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Mentaş HES',
                'uuid' => 'a84d0ebb-d619-4812-9e21-ae84619cc36a',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/mentas-hes/a84d0ebb-d619-4812-9e21-ae84619cc36a',
                'contact' => [
                    [
                        'name'  => 'Rıdvan Yüksel',
                        'title' => 'İşletme Yöneticisi',
                        'email' => 'ridvan.yuksel@aydemenerji.com.tr'
                    ],[
                        'name'  => 'İsmail İbrahimbaş',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'ismail.ibrahimbas@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Göktaş 1 HES',
                'uuid' => '2316628a-96a5-4dd3-8df1-a49bf868b717',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/goktas-1-hes/2316628a-96a5-4dd3-8df1-a49bf868b717',
                'contact' => [
                    [
                        'name'  => 'Burak Muhittin Gençer',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'burak.gencer@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Emre Can Dinler',
                        'title' => 'İşletme Mühendisi',
                        'email' => 'emrecan.dinler@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Göktaş 2 HES',
                'uuid' => '9112f592-c2bd-4c92-be0a-bed1a31458bf',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/goktas-2-hes/9112f592-c2bd-4c92-be0a-bed1a31458bf',
                'contact' => [
                    [
                        'name'  => 'Yunus Emre Şahin',
                        'title' => 'İşletme Müdürü',
                        'email' => 'yunusemre.sahin@aydemenerji.com.tr'
                    ],
                    [
                        'name'  => 'Özer Kurt',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'ozer.kurt@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Akıncı HES',
                'uuid' => '17c38ad1-4efb-4e05-bb6b-715bed3499d9',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/akinci-hes/17c38ad1-4efb-4e05-bb6b-715bed3499d9',
                'contact' => [
                    [
                        'name'  => 'Yasin Kundakçı',
                        'title' => 'İşletme Müdürü',
                        'email' => 'yasin.kundakci@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Şeref Altuğ',
                        'title' => 'İdari İşler Kıdemli Uzmanı',
                        'email' => 'seref.altug@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Koyulhisar HES',
                'uuid' => '826b1527-a95d-40f0-b497-20c0d7afa02c',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/koyulhisar-hes/826b1527-a95d-40f0-b497-20c0d7afa02c',
                'contact' => [
                    [
                        'name'  => 'İhsan Şeker',
                        'title' => 'İşletme Müdürü',
                        'email' => 'ihsan.seker@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Ahmet Yıldız',
                        'title' => 'İdari İşler Kıdemli Uzmanı',
                        'email' => 'ahmet.yildiz@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Çırakdamı HES',
                'uuid' => '9fd3d532-7298-4af0-a3ba-ed13fbbd7215',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/cirakdami-hes/9fd3d532-7298-4af0-a3ba-ed13fbbd7215',
                'contact' => [
                    [
                        'name'  => 'Eren Özkaya',
                        'title' => 'İşletme Müdürü',
                        'email' => 'eren.ozkaya@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Ali Karahan',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'ali.karahan@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Dereli HES',
                'uuid' => '643a1e0c-a922-494b-8fb6-9b40b84be005',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/dereli-hes/643a1e0c-a922-494b-8fb6-9b40b84be005',
                'contact' => [
                    [
                        'name'  => 'Muhammet Bay',
                        'title' => 'İdari İşler Uzman Yardımcısı',
                        'email' => 'muhammet.bay@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Aksu HES',
                'uuid' => '1436769c-7e69-4bda-a2eb-e5d5c357e58c',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/aksu-hes/1436769c-7e69-4bda-a2eb-e5d5c357e58c',
                'contact' => [
                    [
                        'name'  => 'Malik Sağlam',
                        'title' => 'İşletme Yöneticisi',
                        'email' => 'malik.saglam@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Murat Akkara',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'murat.akkara@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Yalova RES',
                'uuid' => 'c022905d-6f27-4f28-b119-74709f39f1d5',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/yalova-res/c022905d-6f27-4f28-b119-74709f39f1d5',
                'contact' => [
                    [
                        'name'  => 'Mustafa Kahraman',
                        'title' => 'İşletme Yöneticisi',
                        'email' => 'mustafa.kahraman@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Ferhat Yorgun',
                        'title' => 'İdari İşler Kıdemli Uzmanı',
                        'email' => 'ferhat.yorgun@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Ufuk Badak',
                        'title' => 'İşletme Mühendisi',
                        'email' => 'ufuk.badak@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Uşak RES',
                'uuid' => '6318a65a-8568-4f92-84e8-18081758ce9c',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/usak-res/6318a65a-8568-4f92-84e8-18081758ce9c',
                'contact' => [
                    [
                        'name'  => 'Harun Güler',
                        'title' => 'İdari İşler Kıdemli Uzmanı',
                        'email' => 'harun.guler@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Ahmet Şengül',
                        'title' => 'İdari İşler Kıdemli Uzmanı',
                        'email' => 'ahmet.sengul@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Mustafa Cem Yüce',
                        'title' => 'İşletme Mühendisi',
                        'email' => 'mustafa.yuce@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Bereket 1 HES',
                'uuid' => 'e57ba44f-6410-4f4b-8fe4-1b5eada54f19',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/bereket-1-hes/e57ba44f-6410-4f4b-8fe4-1b5eada54f19',
                'contact' => [
                    [
                        'name'  => 'İbrahim Ethem Koparan',
                        'title' => 'İşletme Yöneticisi',
                        'email' => 'ethem.koparan@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Ahmet Sağıçımak',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'ahmet.sagicimak@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Bereket 2 HES',
                'uuid' => '0bdf972f-a6d3-4f09-8181-1d390a6e22df',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/bereket-2-hes/0bdf972f-a6d3-4f09-8181-1d390a6e22df',
                'contact' => [
                    [
                        'name'  => 'Akif Yaman',
                        'title' => 'İdari İşler Uzman Yardımcısı',
                        'email' => 'akif.yaman@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Isı Merkezi',
                'uuid' => '2f72f660-4dfa-4142-9575-edc261496255',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/isi-merkezi/2f72f660-4dfa-4142-9575-edc261496255',
                'contact' => [
                    [
                        'name'  => 'Selman Cem Şahin',
                        'title' => 'İşletme Yöneticisi',
                        'email' => 'cem.sahin@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Basri Sarı',
                        'title' => 'İdari İşler Uzman Yardımcısı',
                        'email' => 'basri.sari@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Kızıldere JES',
                'uuid' => 'c318788a-4f23-4b76-b8ed-b13e3f5edcaf',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/kizildere-jes/c318788a-4f23-4b76-b8ed-b13e3f5edcaf'
            ],
            [
                'name' => 'Feslek HES',
                'uuid' => 'f72a8679-ddb5-4650-819c-d9f45c2c7c28',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/feslek-hes/f72a8679-ddb5-4650-819c-d9f45c2c7c28'
            ],
            [
                'name' => 'Adıgüzel HES',
                'uuid' => 'b8c53bf8-36d5-4a43-84a9-540597b480cf',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/adiguzel-hes/b8c53bf8-36d5-4a43-84a9-540597b480cf',
                'contact' => [
                    [
                        'name'  => 'Mehmet Pulcu',
                        'title' => 'İdari İşler Uzman Yardımcısı',
                        'email' => 'mehmet.pulcu@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Kemer HES',
                'uuid' => '31f86b13-dc28-4f10-b9f2-93f4a7582fbc',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/kemer-hes/31f86b13-dc28-4f10-b9f2-93f4a7582fbc',
                'contact' => [
                    [
                        'name'  => 'Fatih Başaran',
                        'title' => 'İşletme Yöneticisi',
                        'email' => 'fatih.basaran@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Recai Karamanlıoğlu',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'recai.karamanlioglu@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Söke RES',
                'uuid' => '2efa0278-4e49-496e-b18e-cce2a75e78b5',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/soke-res/2efa0278-4e49-496e-b18e-cce2a75e78b5',
                'contact' => [
                    [
                        'name'  => 'Ali İlker Özkul',
                        'title' => 'İşletme Müdürü',
                        'email' => 'ali.ozkul@aydemenerji.com.tr'
                    ],[
                        'name'  => 'İbrahim Dilber',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'ibrahim.dilber@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Gökyar HES',
                'uuid' => '96078778-6496-4d20-9167-40bf014e7431',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/gokyar-hes/96078778-6496-4d20-9167-40bf014e7431',
                'contact' => [
                    [
                        'name'  => 'Sinan Kocabıyık',
                        'title' => 'İşletme Müdürü',
                        'email' => 'sinan.kocabiyik@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Çağrı Çengel',
                        'title' => 'İşletme Mühendisi',
                        'email' => 'cagricengel@aydemenerji.com.tr'
                    ]
                ]
            ],
            [
                'name' => 'Dalaman 1 HES',
                'uuid' => 'c495b4bf-22b8-49fc-9969-a9f4b9db02ad',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/dalaman-1-hes/c495b4bf-22b8-49fc-9969-a9f4b9db02ad'
            ],
            [
                'name' => 'Dalaman 2 HES',
                'uuid' => '83c3e5df-8ed2-415c-b554-70665a56b706',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/dalaman-2-hes/83c3e5df-8ed2-415c-b554-70665a56b706'
            ],
            [
                'name' => 'Dalaman 3 HES',
                'uuid' => 'fea71e55-2b1f-44d5-b606-164b88cd2682',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/dalaman-3-hes/fea71e55-2b1f-44d5-b606-164b88cd2682'
            ],
            [
                'name' => 'Dalaman 4 HES',
                'uuid' => '67897cc9-d60c-48d0-bfeb-4b56a949e358',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/dalaman-4-hes/67897cc9-d60c-48d0-bfeb-4b56a949e358'
            ],
            [
                'name' => 'Dalaman 5 HES',
                'uuid' => '184af4b7-3366-4e26-bccc-116bbf3fa183',
                'url' => 'https://ziyaretci.aydemyenilenebilir.com.tr/ziyaretci/dalaman-5-hes/184af4b7-3366-4e26-bccc-116bbf3fa183',
                'contact' => [
                    [
                        'name'  => 'Cem Koray Türedi',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'koray.turedi@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Kenan Şahin',
                        'title' => 'İdari İşler Uzmanı',
                        'email' => 'kenan.sahin@aydemenerji.com.tr'
                    ],[
                        'name'  => 'Necdet Nabi Arslan',
                        'title' => 'İşletme Mühendisi',
                        'email' => 'necdetnabi.arslan@aydemenerji.com.tr'
                    ]
                ]
            ]
        ];

        foreach($facilities as $p){
            $uniqueCode = uniqid();

            $entities = [
                'qr_code' => $p['url'],
                'title' => $p['name'],
                'question_must_know' => '3',
                'address' => $p['name'],
                'status**videogroup**SEEDV'.$uniqueCode.'-0' => '1',
                'videoitem**videogroup**SEEDV'.$uniqueCode.'-0' => '1',

                'question**questiongroup**SEED1'.$uniqueCode.'-0' => 'Aşağıdakilerden hangisi ziyaretçilerin asgari olarak kullanılması zorunlu KKD’lerden biri değildir?',
                'answer**questiongroup**SEED1'.$uniqueCode.'-0' => 'A-) Baret',
                'answer**questiongroup**SEED1'.$uniqueCode.'-0-1' => 'B-) İş Güvenliği Ayakkabısı',
                'answer**questiongroup**SEED1'.$uniqueCode.'-0-2' => 'C-) Kulaklık (gürültülü alanlarda)',
                'answer**questiongroup**SEED1'.$uniqueCode.'-0-3' => 'D-) İzole Eldiven',
                'rightletter**questiongroup**SEED1'.$uniqueCode.'-0' => 'D',

                'question**questiongroup**SEED2'.$uniqueCode.'-0' => 'Yaşanan ramak kala olayları ve tespit edilen tehlikeli durum/davranışlar için en doğru yaklaşım hangisidir?',
                'answer**questiongroup**SEED2'.$uniqueCode.'-0' => 'A-) Bildirmek',
                'answer**questiongroup**SEED2'.$uniqueCode.'-0-1' => 'B-) Gizlemek',
                'answer**questiongroup**SEED2'.$uniqueCode.'-0-2' => 'C-) Göz Ardı Etmek',
                'answer**questiongroup**SEED2'.$uniqueCode.'-0-3' => 'D-) Başkasına Bırakmak',
                'rightletter**questiongroup**SEED2'.$uniqueCode.'-0' => 'A',

                'question**questiongroup**SEED3'.$uniqueCode.'-0' => 'İşletmede yer alan uyarı ve ikaz levhalarının temel amacı nedir?',
                'answer**questiongroup**SEED3'.$uniqueCode.'-0' => 'A-) Çalışanları ve ziyaretçileri tehlikelere karşı bilgilendirmek ve yönlendirmek',
                'answer**questiongroup**SEED3'.$uniqueCode.'-0-1' => 'B-) Alanların dekoratif görünmesini sağlamak',
                'answer**questiongroup**SEED3'.$uniqueCode.'-0-2' => 'C-) Yalnızca ziyaretçilerin girişini kısıtlamak',
                'answer**questiongroup**SEED3'.$uniqueCode.'-0-3' => 'D-) Görsel Amaçlı Kullanmak',
                'rightletter**questiongroup**SEED3'.$uniqueCode.'-0' => 'A',


                'question**questiongroup**SEED4'.$uniqueCode.'-0' => 'Bir acil durum meydana geldiğinde toplanma alanına ulaşırken çalışanların öncelikli davranışı nedir?',
                'answer**questiongroup**SEED4'.$uniqueCode.'-0' => 'A-) Panik Yaparak Koşmak',
                'answer**questiongroup**SEED4'.$uniqueCode.'-0-1' => 'B-) Hızlı adımlarla, panik yapmadan ve yönlendirme levhalarını takip etmek',
                'answer**questiongroup**SEED4'.$uniqueCode.'-0-2' => 'C-) Kendi bildiği kısa yoldan gitmek',
                'answer**questiongroup**SEED4'.$uniqueCode.'-0-3' => 'D-) Telefonla haber verip beklemek',
                'rightletter**questiongroup**SEED4'.$uniqueCode.'-0' => 'B',

                'question**questiongroup**SEED5'.$uniqueCode.'-0' => 'Aşağıdaki atıklardan hangisi sıfır atık geri dönüşüm kutularına atılması gereken atıklardan değildir?',
                'answer**questiongroup**SEED5'.$uniqueCode.'-0' => 'A-) Cam atıklar',
                'answer**questiongroup**SEED5'.$uniqueCode.'-0-1' => 'B-) Kağıt atıklar',
                'answer**questiongroup**SEED5'.$uniqueCode.'-0-2' => 'C-) Atık yağlar',
                'answer**questiongroup**SEED5'.$uniqueCode.'-0-3' => 'D-) Plastik atıklar',
                'rightletter**questiongroup**SEED5'.$uniqueCode.'-0' => 'C',

                ////

                'title--lng--en' =>  $p['name'].' EN',
                'question_must_know--lng--en' => '3',
                'qr_code--lng--en' =>  $p['url'],
                'address--lng--en' => $p['name'].' EN',
                'status--lng--en**videogroup**SEEDV'.$uniqueCode.'-0' => '1',
                'videoitem--lng--en**videogroup**SEEDV'.$uniqueCode.'-0' => '2',

                'question--lng--en**questiongroup**SEED1'.$uniqueCode.'-0' => 'Which of the following is not one of the minimum PPE requirements?',
                'answer--lng--en**questiongroup**SEED1'.$uniqueCode.'-0' => 'A-) Hard Hat',
                'answer--lng--en**questiongroup**SEED1'.$uniqueCode.'-0-1' => 'B-) Work Safety Shoes',
                'answer--lng--en**questiongroup**SEED1'.$uniqueCode.'-0-2' => 'C-) Headphones (in noisy areas)',
                'answer--lng--en**questiongroup**SEED1'.$uniqueCode.'-0-3' => 'D-) Insulated Gloves',
                'rightletter--lng--en**questiongroup**SEED1'.$uniqueCode.'-0' => 'D',

                'question--lng--en**questiongroup**SEED2'.$uniqueCode.'-0' => 'What is the most appropriate approach for near-miss incidents and detected dangerous situations/behaviors?',
                'answer--lng--en**questiongroup**SEED2'.$uniqueCode.'-0' => 'A-) Report',
                'answer--lng--en**questiongroup**SEED2'.$uniqueCode.'-0-1' => 'B-) Hide',
                'answer--lng--en**questiongroup**SEED2'.$uniqueCode.'-0-2' => 'C-) Ignore',
                'answer--lng--en**questiongroup**SEED2'.$uniqueCode.'-0-3' => 'D-) Leaving it to Someone Else',
                'rightletter--lng--en**questiongroup**SEED2'.$uniqueCode.'-0' => 'A',

                'question--lng--en**questiongroup**SEED3'.$uniqueCode.'-0' => 'What is the main purpose of the warning and caution signs in the business?',
                'answer--lng--en**questiongroup**SEED3'.$uniqueCode.'-0' => 'A-) Informing and guiding employees and visitors against dangers',
                'answer--lng--en**questiongroup**SEED3'.$uniqueCode.'-0-1' => 'B-) Making areas look decorative',
                'answer--lng--en**questiongroup**SEED3'.$uniqueCode.'-0-2' => 'C-) Restricting entry to visitors only',
                'answer--lng--en**questiongroup**SEED3'.$uniqueCode.'-0-3' => 'D-) Use for Visual Purposes',
                'rightletter--lng--en**questiongroup**SEED3'.$uniqueCode.'-0' => 'A',


                'question--lng--en**questiongroup**SEED4'.$uniqueCode.'-0' => 'What is the priority behavior of employees when reaching the assembly area in case of an emergency?',
                'answer--lng--en**questiongroup**SEED4'.$uniqueCode.'-0' => 'A-) Running in Panic',
                'answer--lng--en**questiongroup**SEED4'.$uniqueCode.'-0-1' => 'B-) Take quick steps, without panicking, and follow the direction signs.',
                'answer--lng--en**questiongroup**SEED4'.$uniqueCode.'-0-2' => 'C-) Take the shortcut you know best',
                'answer--lng--en**questiongroup**SEED4'.$uniqueCode.'-0-3' => 'D-) Calling and waiting',
                'rightletter--lng--en**questiongroup**SEED4'.$uniqueCode.'-0' => 'B',

                'question--lng--en**questiongroup**SEED5'.$uniqueCode.'-0' => 'Which of the following wastes is not one of the wastes that should be thrown into zero waste recycling bins?',
                'answer--lng--en**questiongroup**SEED5'.$uniqueCode.'-0' => 'A-) Glass waste',
                'answer--lng--en**questiongroup**SEED5'.$uniqueCode.'-0-1' => 'B-) Paper waste',
                'answer--lng--en**questiongroup**SEED5'.$uniqueCode.'-0-2' => 'C-) Residual oils',
                'answer--lng--en**questiongroup**SEED5'.$uniqueCode.'-0-3' => 'D-) Plastic waste',
                'rightletter--lng--en**questiongroup**SEED5'.$uniqueCode.'-0' => 'C',
            ];

            //add contacts here
            if(isset($p['contact'])){
                for($i = 0 ; $i < count($p['contact']);$i++){
                    $entities['supervisor**facilitycontactgroup**SEED'.$i.$uniqueCode.'-'.$i]    = $p['contact'][$i]['name'];
                    $entities['job**facilitycontactgroup**SEED'.$i.$uniqueCode.'-'.$i]           = $p['contact'][$i]['title'];
                    $entities['contact_mail**facilitycontactgroup**SEED'.$i.$uniqueCode.'-'.$i]  = $p['contact'][$i]['email'];
                }
            }

            $data = [
                'dynamicF' => [
                    'op-doc-facility-form**new' => [
                        'entities' => $entities,
                        'tag' => 'op-doc-facility-form',
                    ],
                ],
                'removedData' => [],
                'typeKey' => 'op-doc-facility',
            ];

            $res = (new DocumentServiceProvider())->registerContent(0,$data,[]);
            //print_r($res);
            print_r($p['name'].' Added..'.PHP_EOL);
            
        }

        $inventories = [
            [
                'title' => 'Baret',
                'code'  => 'baret1',
            ],[
                'title' => 'Reflektörlü Yelek',
                'code'  => 'yelek123',
            ],[
                'title' => 'İş Güvenliği Ayakkabısı',
                'code'  => 'ayakkabı1',
            ],[
                'title' => 'Kulaklık (Gürültülü Alanlar için)',
                'code'  => 'kulaklık1',
            ]
        ];

        foreach($inventories as $i){
            $data = [
                'dynamicF' => [
                    'op-doc-inventory-form**new' => [
                        'entities' => [
                            'code'           => $i['code'],
                            'title'          => $i['title'],
                            'title--lng--en' => $i['title'],
                            'code--lng--en'  => $i['code'],
                        ],
                        'tag' => 'op-doc-inventory-form',
                    ],
                ],
                'removedData' => [],
                'typeKey' => 'op-doc-inventory',
            ];

            $res = (new DocumentServiceProvider())->registerContent(0,$data,[]);
            //print_r($res);
            print_r($i['title'].' Added..'.PHP_EOL);
        }
    }
    
}
