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
        $this->seedFlats();
    }

    private function seedFlats($count = 8,$blocks = ['A','B']){

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

                print_r("$b-".($i+1).' Added..'.PHP_EOL);
            }
        }
    }
    
}
