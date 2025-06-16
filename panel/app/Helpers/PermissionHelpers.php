<?php
use App\Providers\EncryptionProvider;
use Illuminate\Support\Facades\Session;


if(!function_exists('checkPerm')){
    function checkPerm($key){
        //return true;
        return session('sper-'.$key) !== null || (intval(auth('sanctum')->user()->person_id) == 0) ? true : false;
    }
}



if(!function_exists('preUp')){
    function preUp($str){
        $str = str_replace('i', 'İ', $str);
        $str = str_replace('ı', 'I', $str);
        $str = str_replace('ö', 'Ö', $str);
        $str = str_replace('o', 'O', $str);
        $str = str_replace('ü', 'Ü', $str);
        $str = str_replace('u', 'U', $str);
        /*$str = str_replace('ş', 'S', $str);
        $str = str_replace('Ş', 'S', $str);*/
        return $str;
    }
}



if(!function_exists('noInject')){
    
    function noInject($kelime) {
        $kelime = str_replace("'","''",$kelime);
        $kelime = str_replace("--","_",$kelime);
        $kelime = str_replace("/*"," ",$kelime);
        $kelime = str_replace("*/"," ",$kelime);
        $kelime = str_replace(";"," ",$kelime);
        $kelime = str_replace('"',"''",$kelime);
        $kelime = str_replace(" or "," kor ",$kelime);
        
        $kelime = str_replace(" OR "," KOR ",$kelime);
        
        $kelime = str_replace(" Or "," Kor ",$kelime);
       
        $kelime = str_replace(" drop "," drp ",$kelime);
        $kelime = str_replace(" DROP "," DRP ",$kelime);
        $kelime = str_replace(" Drop "," DRP ",$kelime);
        $kelime = str_replace(" alter "," atr ",$kelime);
        $kelime = str_replace(" ALTER "," atr ",$kelime);
        $kelime = str_replace(" Alter "," atr ",$kelime);

        $kelime = str_replace(" AND "," an-d ",$kelime);
        $kelime = str_replace(" And "," an-d ",$kelime);
        $kelime = str_replace(" and "," an-d ",$kelime);

        $kelime = str_replace(" FROM "," frm ",$kelime);
        $kelime = str_replace(" From "," frm ",$kelime);
        $kelime = str_replace(" from "," frm ",$kelime);

        $kelime = str_replace(" select "," slct ",$kelime);
        $kelime = str_replace(" Select "," slct ",$kelime);
        $kelime = str_replace(" SELECT "," slct ",$kelime);

        $kelime = str_replace(" INSERT "," intt ",$kelime);
        $kelime = str_replace(" UPDATE "," upt ",$kelime);
        $kelime = str_replace(" DELETE "," dlt ",$kelime);

        $kelime = str_replace("HTTP://","url:",$kelime);
        $kelime = str_replace("http://","url:",$kelime);
        $kelime = str_replace("href=","falan=",$kelime);
        return $kelime;
    }
}

if(!function_exists('checkPermRoute')){
    
}

if(!function_exists('hasMailPerm')){
    function hasMailPerm($personId,$key){
        $sql = "select * from document_con_ops uco 
                    inner join sys_options so on so.id = uco.type_id 
                    inner join sys_options so2 on so2.id = uco.conn_id
                    inner join users u on u.id = uco.main_id 
                        where so.op_key = 'op-perm' and so2.op_key = '".$key."' and u.person_id = '".$personId."'";
        $results = \Illuminate\Support\Facades\DB::select($sql);
       
        return [
            'success' => count($results) !== 0
        ]; 
    }
}



?>