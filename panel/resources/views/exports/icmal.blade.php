<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style type="text/css" media="all">
        table, th, td {
            border: 1px solid black;
            text-align: center;
        }
        table{
            width: 100%;
        }
        body { font-family: DejaVu Sans, sans-serif; }
        .pagebreak { page-break-before: always; } /* page-break-after works, as well */
    </style>
    <title>Document</title>
</head>
<body>  
        <div style="width:100%">
            
            <h4 style="text-align:center">Körfez Apt. {{$dates}} Dönemi Bilançosu</h4>
            @php
                $datess = explode(' - ',$dates);
                $datess[0] = implode('-',array_reverse(explode('/',$datess[0])));
                $datess[1] = implode('-',array_reverse(explode('/',$datess[1])));    

            @endphp
            <h5>(Hesaplar {{date('Y-m-t',strtotime($datess[1].'-01'))}} tarihli kapanışlardır.)</h5>
            
            <table>
                <thead>
                    <th>Hesap</th>
                    <th>Miktar</th>
                    <th>Açıklama</th>
                </thead>
                <tbody>
                    
                        @for($i = 0; $i < count($accounts['data']) ; $i++)
                            @php
                            $info = json_decode($accounts['data'][$i]->main_attr,true);
                            foreach($info as $k){
                                if($k['Key'] == 'title')        $accounts['data'][$i]->title = $k['Value'];
                                if($k['Key'] == 'currency')     $accounts['data'][$i]->currency = $k['Value'];
                                if($k['Key'] == 'target_note')  $accounts['data'][$i]->note = $k['Value'];
                            }
                            @endphp
                            <tr>
                                <td style="text-align:left">{{$accounts['data'][$i]->title}}</td>
                                <td style="text-align: end;">{{($accounts['data'][$i]->balance_pure ?? 0).' '.$accounts['data'][$i]->currency}}</td>
                                <td>{{$accounts['data'][$i]->note ?? ''}}</td>
                            </tr>
                        @endfor
                </tbody>
            </table>
            <h4 style="text-align:center">Körfez Apt. {{$dates}} Dönemi Giderleri</h4>
            
            <table>
                <tbody>
                    <tr>
                        <td style="text-align: start;">Toplam Gider</td>
                        <td style="text-align: end;"></td>
                        <td style="text-align: end;">{{number_format($outcomes['data'], 2, ',', '.').' '.$outcomes['cur']}}</td>
                        
                    </tr>
                    @foreach($outcomes['list'] as $row)
                        <tr>
                            <td style="text-align: start;">{{$row->title}}</td>
                            <td style="text-align: end;">{{$row->note ?? ''}}</td>
                            <td style="text-align: end;">{{number_format($row->amount, 2, ',', '.').' '.$outcomes['cur']}}</td>
                        </tr>
                    @endforeach
                        
                </tbody>
            </table>
            <h4 style="text-align:center">Körfez Apt. {{$dates}} Dönemi Gelirleri</h4>
            @php 
                $depts = [];
            @endphp
            <table>
                <tbody>
                    <tr>
                        <td style="text-align: start;">Toplam Gelir</td>
                        <td style="text-align: end;">{{number_format($incomes['data'], 2, ',', '.').' '.$incomes['cur']}}</td>
                    </tr>
                    @php 
                        $incomeList = [];
                        foreach ($incomes['list'] as $row) {
                            
                            if($row->op_key == 'doc_acc_dept'){
                                $info = json_decode($row->main_info);
                                $info[2] = empty($info[2] ?? '') ? $info[0] : $info[2]; 

                                $accInfo = json_decode($row->acc_info);
                                
                                if(!isset($depts[trim($accInfo[0])])) $depts[trim($accInfo[0])] = [];

                                if(!isset($depts[trim($accInfo[0])][trim($info[2])])) $depts[trim($accInfo[0])][trim($info[2])] = 0;
                               
                                $depts[trim($accInfo[0])][trim($info[2])] += $row->amount;
                            }else{
                                if(!isset($incomeList[trim($row->title)])) $incomeList[trim($row->title)] = 0;
                                $incomeList[trim($row->title)] += $row->amount;
                            }
                        }
                    @endphp

                    @foreach($incomeList as $k => $v)
                        <tr>
                            <td style="text-align: start;">{{$k}}</td>
                            <td style="text-align: end;">{{number_format($v, 2, ',', '.').' '.$incomes['cur']}}</td>
                        </tr>
                    @endforeach
                        
                </tbody>
            </table>
            <h4 style="text-align:center">Körfez Apt. {{$dates}} Dönemi Kira ve Aidat Borcu Olanlar</h4>
            <table>
                <tbody>
                    @foreach($depts as $k => $v)
                        @php $i = 0; @endphp
                        @foreach($v as $p => $a)
                        <tr>
                            @if($i == 0)
                            <td style="text-align: start;" rowspan="{{count($v)}}">{{$k}}</td>
                            @endif
                            <td style="text-align: start;">{{$p}}</td>
                            <td style="text-align: end;">{{number_format($a, 2, ',', '.').' '.$incomes['cur']}}</td>
                        </tr>
                         @php $i ++; @endphp
                        @endforeach
                    @endforeach
                        
                </tbody>
            </table>
        </div>
</body>
</html>