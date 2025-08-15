<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Providers\DocumentServiceProvider;

class StressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedFlats();
        $this->seedTransactions();
    }

    private function seedFlats($count = 500,$blocks = ['A','B']){

        foreach($blocks as $b){
            for($i = 0;$i < $count; $i++ ){
            
                $data = array(
                    'dynamicF' => array(
                        'op-doc-flat-form**new-'.$i.$b => array(
                            'entities' => array(
                                    'title' => "$b-".($i+1),
                                    /*'cont_name**flatcontgroup**'.$i.$b.'-0' => 'Girilmedi',
                                    'cont_phone**flatcontgroup**'.$i.$b.'-0' => '(000) 000-00-00'*/
                            ),
                            'tag' => 'op-doc-flat-form'
                        )
                    ),
                    'typeKey' => 'op-doc-flat'
                );

                $res = (new DocumentServiceProvider())->registerContent(0,$data,[]);
                //print_r($res);
                print_r("$b-".($i+1).' Added..'.PHP_EOL);
            }
        }
    }

    private function seedTransactions(){
        
        for($i = 0;$i < 10000; $i++ ){
            $res = (new DocumentServiceProvider())->setPayment([
                'target_id'  => '3bbd3222-df41-4571-958d-a8a8fb535720',
                'op'         => 'addbalance',
                'amount'     => '200',
                'currency'   => 'TRY',
                'period'     => '2025-01',
                'note'       => 'FALAN'
            ],[]);

            print_r(($i+1).' Trans Added..'.PHP_EOL);
        }
    }
    
}
