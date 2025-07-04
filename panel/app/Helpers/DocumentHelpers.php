<?php
use App\Providers\EncryptionProvider;

if(!function_exists('signDocument')){
    function signDocument($path,$data,$ftrans = 'download',$fname = '-'){
        try{

            $text = "Bu Belge '".explode('-**-',$data)[2]."' Tarafından '".date_format(date_create(explode('-**-',$data)[1]),'d.m.Y')."' Tarihinde Onaylanmıştır.";
            
            //first check file type of document
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            if($finfo->file($path) == 'application/pdf'){
                $fpdi = new \setasign\Fpdi\Fpdi;
                $fpdi->AddFont('Arial','','arial.php');
                


                $count = $fpdi->setSourceFile($path);
                
                for ($i=1; $i<=$count; $i++) {
        
                    $template = $fpdi->importPage($i);
                    $size = $fpdi->getTemplateSize($template);
                    $fpdi->AddPage($size['orientation'], array($size['width'], $size['height']));
                    $fpdi->useTemplate($template);
                    
                    $fpdi->SetFont("Arial", "", 15);
                    $fpdi->SetTextColor(255,100,100);
        
                    $left = (intval($size['width']) / 2)-75;
                    $top =  intval($size['height'])-5;
                    
                    $fpdi->Text($left,$top,iconv('UTF-8','iso-8859-9',$text));
                }
        
                

                if($ftrans == 'download'){
                    $fpdi->Output('D',$fname); 
                }else{
                    $fpdi->Output();
                }

                die;
            }else{
                
                $img = \Intervention\Image\Facades\Image::make($path);  

                $img->text($text, 100 /* x */,$img->height() - 100 /* y */, function($font) {
                    $font->file(public_path('/system/front/font/Roboto-Medium.ttf') );
                    $font->size(50);
                    $font->color('#ee2e31');

                });
                return $img->encode('webp', 95);
            }
        }catch(Exception $e){
            if($e->getCode() == 267){
                $newPath = $path.'lowered.pdf';
                $command = 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dBATCH -sOutputFile="'.$newPath.'" "'.$path.'" 2>&1';
                exec($command);
                signDocument($newPath,$data,$ftrans,$fname);
            }else{
                return false;
            }
        }
    }
}

if(!function_exists('decryptFile')){
    function decryptFile($doc,$ftrans = 'download' ){
        $enc = new \App\Providers\EncryptionProvider();
        $path = storage_path('app/public') . '/documents/' . $enc->decrypt($doc);
        
        $response = null;
        if (file_exists($path)){   
            
            //check for supplier permission
            //$personId = auth('sanctum')->user()->person_id;
            //get person type
            
            //$person = \App\Models\Persons::where('id',$personId)->first();
            
            //check permission for document view
            //if(!checkPerm('per-19') && !checkPerm('per-07')) abort(404);

            /*if(intval($person->type_id) != 2){
                
                //filter order documents
                $order_id = 0;
                //first find document
                $doc = \App\Models\Document_files::where('description',$doc)->first();
                //find order 
                //if is product image then get order id from product
                $op  = \App\Models\Sys_options::where('id',$doc->op_id)->first();
                if(trim($op->op_key) == 'op-prd-doc'){
                    $order_id = (\App\Models\Document_details::where('id',$doc->document_id)->first())->document_id;
                }else{
                    $order_id = $doc->document_id;
                }
            
                $order = \App\Models\Documents::where('id',$order_id)->first();


                //get person client codes
                $codes = \App\Models\Persons::where('parent_id',$personId)->get();
                $codeList = [];
                foreach ($codes as $code) {
                    $codeList[] = trim($code->spec_code);
                }

                //check if code list includes order client code
                if (!in_array(trim($order->spec_code), $codeList)) {
                    abort(404);
                }
            }   */


            $file = File::get($path);
            $type = File::mimeType($path);
            $name = File::name($path).'.'.getExtension($type);/*File::mimeType($path);*/

            //now check if is approved for someone 
            /*$trans = isApproved($doc);
            if($trans['success']){
                //check file type here
                $rsp = signDocument($path,$trans['data'],$ftrans,$name);
                if($rsp !== false) $file = $rsp;
            }*/
            
            $headers = [
                'Content-type'        => $type,
            ];

            if($ftrans == 'download') $headers['Content-Disposition'] = 'attachment; filename="'.$name.'"';
            
            $response = Response::make($file, 200, $headers);
            

            return $response;
        }else{
            abort(404);
        }
    }

    function getExtension ($mime_type){
        $extensions = [
            'application/bmp'=>'bmp',
            'application/cdr'=>'cdr',
            'application/coreldraw'=>'cdr',
            'application/excel'=>'xl',
            'application/gpg-keys'=>'gpg',
            'application/java-archive'=>'jar',
            'application/json'=>'json',
            'application/mac-binary'=>'bin',
            'application/mac-binhex'=>'hqx',
            'application/mac-binhex40'=>'hqx',
            'application/mac-compactpro'=>'cpt',
            'application/macbinary'=>'bin',
            'application/msexcel'=>'xls',
            'application/msword'=>'doc',
            'application/octet-stream'=>'pdf',
            'application/oda'=>'oda',
            'application/ogg'=>'ogg',
            'application/pdf'=>'pdf',
            'application/pgp'=>'pgp',
            'application/php'=>'php',
            'application/pkcs-crl'=>'crl',
            'application/pkcs10'=>'p10',
            'application/pkcs7-mime'=>'p7c',
            'application/pkcs7-signature'=>'p7s',
            'application/pkix-cert'=>'crt',
            'application/pkix-crl'=>'crl',
            'application/postscript'=>'ai',
            'application/powerpoint'=>'ppt',
            'application/rar'=>'rar',
            'application/s-compressed'=>'zip',
            'application/smil'=>'smil',
            'application/videolan'=>'vlc',
            'application/vnd.google-earth.kml+xml'=>'kml',
            'application/vnd.google-earth.kmz'=>'kmz',
            'application/vnd.mif'=>'mif',
            'application/vnd.mpegurl'=>'m4u',
            'application/vnd.ms-excel'=>'xlsx',
            'application/vnd.ms-office'=>'ppt',
            'application/vnd.ms-powerpoint'=>'ppt',
            'application/vnd.msexcel'=>'csv',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'pptx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
            'application/wbxml'=>'wbxml',
            'application/wmlc'=>'wmlc',
            'application/x-binary'=>'bin',
            'application/x-binhex40'=>'hqx',
            'application/x-bmp'=>'bmp',
            'application/x-cdr'=>'cdr',
            'application/x-compress'=>'z',
            'application/x-compressed'=>'7zip',
            'application/x-coreldraw'=>'cdr',
            'application/x-director'=>'dcr',
            'application/x-dos_ms_excel'=>'xls',
            'application/x-dvi'=>'dvi',
            'application/x-excel'=>'xls',
            'application/x-gtar'=>'gtar',
            'application/x-gzip'=>'gzip',
            'application/x-gzip-compressed'=>'tgz',
            'application/x-httpd-php'=>'php',
            'application/x-httpd-php-source'=>'php',
            'application/x-jar'=>'jar',
            'application/x-java-application'=>'jar',
            'application/x-javascript'=>'js',
            'application/x-mac-binhex40'=>'hqx',
            'application/x-macbinary'=>'bin',
            'application/x-ms-excel'=>'xls',
            'application/x-msdownload'=>'exe',
            'application/x-msexcel'=>'xls',
            'application/x-pem-file'=>'pem',
            'application/x-photoshop'=>'psd',
            'application/x-php'=>'php',
            'application/x-pkcs10'=>'p10',
            'application/x-pkcs12'=>'p12',
            'application/x-pkcs7'=>'rsa',
            'application/x-pkcs7-certreqresp'=>'p7r',
            'application/x-pkcs7-mime'=>'p7c',
            'application/x-pkcs7-signature'=>'p7a',
            'application/x-rar'=>'rar',
            'application/x-rar-compressed'=>'rar',
            'application/x-shockwave-flash'=>'swf',
            'application/x-stuffit'=>'sit',
            'application/x-tar'=>'tar',
            'application/x-troff-msvideo'=>'avi',
            'application/x-win-bitmap'=>'bmp',
            'application/x-x509-ca-cert'=>'crt',
            'application/x-x509-user-cert'=>'pem',
            'application/x-xls'=>'xls',
            'application/x-zip'=>'zip',
            'application/x-zip-compressed'=>'zip',
            'application/xhtml+xml'=>'xhtml',
            'application/xls'=>'xls',
            'application/xml'=>'xml',
            'application/xspf+xml'=>'xspf',
            'application/zip'=>'zip',
            'audio/ac3'=>'ac3',
            'audio/aiff'=>'aif',
            'audio/midi'=>'mid',
            'audio/mp3'=>'mp3',
            'audio/mp4'=>'m4a',
            'audio/mpeg'=>'mp3',
            'audio/mpeg3'=>'mp3',
            'audio/mpg'=>'mp3',
            'audio/ogg'=>'ogg',
            'audio/wav'=>'wav',
            'audio/wave'=>'wav',
            'audio/x-acc'=>'aac',
            'audio/x-aiff'=>'aif',
            'audio/x-au'=>'au',
            'audio/x-flac'=>'flac',
            'audio/x-m4a'=>'m4a',
            'audio/x-ms-wma'=>'wma',
            'audio/x-pn-realaudio'=>'ram',
            'audio/x-pn-realaudio-plugin'=>'rpm',
            'audio/x-realaudio'=>'ra',
            'audio/x-wav'=>'wav',
            'font/otf'=>'otf',
            'font/ttf'=>'ttf',
            'font/woff'=>'woff',
            'font/woff2'=>'woff2',
            'image/bmp'=>'bmp',
            'image/cdr'=>'cdr',
            'image/gif'=>'gif',
            'image/jp2'=>'jp2',
            'image/jpeg'=>'jpeg',
            'image/jpm'=>'jp2',
            'image/jpx'=>'jp2',
            'image/ms-bmp'=>'bmp',
            'image/pjpeg'=>'jpeg',
            'image/png'=>'png',
            'image/svg+xml'=>'svg',
            'image/tiff'=>'tiff',
            'image/vnd.adobe.photoshop'=>'psd',
            'image/vnd.microsoft.icon'=>'ico',
            'image/webp'=>'webp',
            'image/x-bitmap'=>'bmp',
            'image/x-bmp'=>'bmp',
            'image/x-cdr'=>'cdr',
            'image/x-ico'=>'ico',
            'image/x-icon'=>'ico',
            'image/x-ms-bmp'=>'bmp',
            'image/x-png'=>'png',
            'image/x-win-bitmap'=>'bmp',
            'image/x-windows-bmp'=>'bmp',
            'image/x-xbitmap'=>'bmp',
            'message/rfc822'=>'eml',
            'multipart/x-zip'=>'zip',
            'text/calendar'=>'ics',
            'text/comma-separated-values'=>'csv',
            'text/css'=>'css',
            'text/html'=>'html',
            'text/json'=>'json',
            'text/php'=>'php',
            'text/plain'=>'txt',
            'text/richtext'=>'rtx',
            'text/rtf'=>'rtf',
            'text/srt'=>'srt',
            'text/vtt'=>'vtt',
            'text/x-comma-separated-values'=>'csv',
            'text/x-log'=>'log',
            'text/x-php'=>'php',
            'text/x-scriptzsh'=>'zsh',
            'text/x-vcard'=>'vcf',
            'text/xml'=>'xml',
            'text/xsl'=>'xsl',
            'video/3gp'=>'3gp',
            'video/3gpp'=>'3gp',
            'video/3gpp2'=>'3g2',
            'video/avi'=>'avi',
            'video/mj2'=>'jp2',
            'video/mp4'=>'mp4',
            'video/mpeg'=>'mpeg',
            'video/msvideo'=>'avi',
            'video/ogg'=>'ogg',
            'video/quicktime'=>'mov',
            'video/vnd.rn-realvideo'=>'rv',
            'video/webm'=>'webm',
            'video/x-f4v'=>'f4v',
            'video/x-flv'=>'flv',
            'video/x-ms-asf'=>'wmv',
            'video/x-ms-wmv'=>'wmv',
            'video/x-msvideo'=>'avi',
            'video/x-sgi-movie'=>'movie',
            'zz-application/zz-winassoc-cdr'=>'cdr'
        ];
    
        // Add as many other Mime Types / File Extensions as you like
    
        return $extensions[$mime_type];
    
    }
}

if(!function_exists('parsePut')){
    function parsePut(){
        global $_PUT;

        /* PUT data comes in on the stdin stream */
        $putdata = fopen("php://input", "r");

        /* Open a file for writing */
        // $fp = fopen("myputfile.ext", "w");

        $raw_data = '';

        /* Read the data 1 KB at a time
        and write to the file */
        while ($chunk = fread($putdata, 1024))
            $raw_data .= $chunk;

        /* Close the streams */
        fclose($putdata);

        // Fetch content and determine boundary
        $boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));

        if(empty($boundary)){
            parse_str($raw_data,$data);
            $GLOBALS[ '_PUT' ] = $data;
            return;
        }

        // Fetch each part
        $parts = array_slice(explode($boundary, $raw_data), 1);
        $data = array();

        foreach ($parts as $part) {
            // If this is the last part, break
            if ($part == "--\r\n") break;

            // Separate content from headers
            $part = ltrim($part, "\r\n");
            list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);

            // Parse the headers list
            $raw_headers = explode("\r\n", $raw_headers);
            $headers = array();
            foreach ($raw_headers as $header) {
                list($name, $value) = explode(':', $header);
                $headers[strtolower($name)] = ltrim($value, ' ');
            }

            // Parse the Content-Disposition to get the field name, etc.
            if (isset($headers['content-disposition'])) {
                $filename = null;
                $tmp_name = null;
                preg_match(
                    '/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
                    $headers['content-disposition'],
                    $matches
                );
                list(, $type, $name) = $matches;

                //Parse File
                if( isset($matches[4]) )
                {
                    //if labeled the same as previous, skip
                    if( isset( $_FILES[ $matches[ 2 ] ] ) )
                    {
                        continue;
                    }

                    //get filename
                    $filename = $matches[4];

                    //get tmp name
                    $filename_parts = pathinfo( $filename );
                    $tmp_name = tempnam( ini_get('upload_tmp_dir'), $filename_parts['filename']);

                    //populate $_FILES with information, size may be off in multibyte situation
                    $_FILES[ $matches[ 2 ] ] = /*array(
                        'error'=>0,
                        'name'=>$filename,
                        'tmp_name'=>$tmp_name,
                        'size'=>strlen( $body ),
                        'type'=>$value
                    )*/new \Symfony\Component\HttpFoundation\File\UploadedFile(
                        $tmp_name,
                        $filename,
                        $value,
                        strlen($body),
                        0,
                        false
                    );

                    //place in temporary directory
                    file_put_contents($tmp_name, $body);
                }
                //Parse Field
                else
                {
                    $data[$name] = substr($body, 0, strlen($body) - 2);
                }
            }

        }
        $GLOBALS[ '_PUT' ] = $data;
        return $_PUT;
    }
}

if(!function_exists('uploadFile')){
    function slugify($text, string $divider = '-'){
        // replace non letter or digits by divider
        $text = preg_replace('~[^\pL\d]+~u', $divider, $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, $divider);

        // remove duplicate divider
        $text = preg_replace('~-+~', $divider, $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }
    
    function uploadFile($file){
        try{
            //check file size and type (40 mb)
            if($file->getSize() <= 42000000 && in_array(strtolower($file->getClientOriginalExtension()),['jpg','png','jpeg','pdf','xls','xlsx']) ){
                $filename = time().(\Illuminate\Support\Str::random(5)).slugify($file->getClientOriginalName()).'.'.$file->getClientOriginalExtension();
                $rsp = Illuminate\Support\Facades\Storage::disk('public')->putFileAs(
                    'documents',
                    $file,
                    $filename
                );
                
                $enc = new EncryptionProvider();
                return [
                    'data' =>$enc->encrypt($filename,'pickle'),
                    'rsp'  => $rsp,
                    'success' => $rsp != false
                ];
            }else{
                return [
                    'msg'     => 'File is not valid',
                    'success' => false
                ];
            }
        }catch(Exception $e){
            return [
                'msg'     => $e->getMessage(),
                'success' => false
            ];
        }
    }

    function removeFile($fileId){
        
        try{
            $file = \App\Models\Document_files::where('id',$fileId)->first();
            $enc = new \App\Providers\EncryptionProvider();
            $path = storage_path('app/public') . '/documents/' . $enc->decrypt($file->description);
            unlink($path);
            return [
                'row'     => $file,
                'success' => true,
            ];
        }catch (Exception $e) {
            return [
                'row'     => [],
                'success' => false,
            ];
        }
        
    }

    function addFileToDb($f,$tag,$rowId = 0,$reletion = '-',$reletion_id = '0',$logMessage = ''){
        $fileRsp = uploadFile($f);
        if($fileRsp['success']){
           
            //get file type id
            $fileType = (\App\Models\Sys_options::where('op_key' , $tag)->first());

            //add document file connection (**file location is in this record **)
            $file              = new \App\Models\Document_files();

            $file->relation_id = $reletion_id;
            $file->relation    = $reletion;
            $file->type_id     = $fileType->id;
            $file->description = $fileRsp['data'];

            $file->save();

           
            //add transaction to file
            /*$log              = new \App\Models\User_logs();
            $log->sys_code    = $GLOBALS['SYS_CODE'] == 'GDZ' ? '4000' : '5000';
            $log->relation    = $reletion;
            $log->relation_id = $reletion_id;
            $log->user_id     = auth('sanctum')->user()->id;
            $log->type_id      = \App\Models\Sys_options::select('id')->where('op_key', 'log-file-added')->first()->id;
            $log->description = json_encode(array(
                'file_id' => $file->id,
                'desc'    => $logMessage == '' ? $fileType->title.' Dosyası Sisteme Eklendi' : $logMessage,
            ),JSON_UNESCAPED_UNICODE);
            $log->save();*/

            \App\Models\Transactions::create([
                'op_id'     => 1,
                'type_id'   => (\App\Models\Sys_options::where('op_key' , 'doc_file_waiting')->first())->id,
                'log_id'    => 0,//$log->id,
                'target_id' => $file->id,
                'description' => 'Person Added New File'
            ]);

            if($rowId != 0){
                $fileOld = \App\Models\Document_files::where('id', $rowId)->first(); // every file needs to stay so make this passive file
                $fileOld->status        = 0;
                $fileOld->replaced_id   = $file->id;
                $fileOld->save();

                \App\Models\Transactions::create([
                    'op_id'     => 1,
                    'type_id'   => (\App\Models\Sys_options::where('op_key' , 'doc_file_refreshed')->first())->id,
                    'log_id'    => $log->id,
                    'target_id' => $fileOld->id,
                    'description' => 'Person Replaced File'
                ]);

                //copy all entities to new file

                $entities = \App\Models\Sys_con_entities::where(['conn_id' => $rowId , 'table_tag' => 'document_files'])->get();
                foreach ($entities as $e) {
                    $entity               = new \App\Models\Sys_con_entities();
                    $entity->table_tag    = 'document_files';
                    $entity->conn_id      = $file->id;
                    $entity->entity_value = $e->entity_value;
                    $entity->entity_tag   = $e->entity_tag;
                    $entity->save();
                }
            }
            
            $rowId = $file->id;

            $fileRsp['rowId'] = $file->id;
        }else{
            $rowId = 0;
        }

        return $fileRsp;
    }
}

if(!function_exists('displayDates')){
    //will get all dates between 2 date
    function displayDates($date1, $date2, $format = 'Y-m-d' ,$step = '+1 day') {
        $dates = array();
        $current = strtotime($date1);
        $date2 = strtotime($date2);
        $stepVal = $step;
        while( $current <= $date2 ) {
            if($format == 'Y-m' || $format == 'Y'){
                $dates[date($format, $current)] = date($format, $current);
            }else{
                $dates[] = date($format, $current);
            }


            
            $current = strtotime($stepVal, $current);
        }
        return $dates;
    }

    function getDates($month,$year){
        $numbers = array('1','2','3','4','5','6','7','8','9');
        $datesArray = array();
        $num_of_days = date('t',mktime (0,0,0,$month,1,$year));
        for( $i=1; $i<= $num_of_days; $i++) {
            if(in_array($i,$numbers)) $i = '0'.$i;
            //complete date in Y-m-d format
            $datesArray[]= $year . "-". $month. "-". $i;
            
        }
        return $datesArray;
    }
    //will check if date is weekend
    function isWeekend($date) {
        $weekDay = date('w', strtotime($date));
        return ($weekDay == 0 || $weekDay == 6);
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