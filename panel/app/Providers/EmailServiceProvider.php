<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendNotificationMailJob;

use App\Jobs\SendResetMailJob;
use App\Jobs\SendInfoMailJob;

class EmailServiceProvider extends ServiceProvider
{
    public function __construct() {
       
    }
    
    public function sendregisterMails($email, $phone){
        try{
            SendNotificationMailJob::dispatch([
                'type' => 'register',
                'email' => $email,
                'phone' => $phone
            ])->onQueue('default');
            Log::info('sendregisterMails dispatched successfully', ['email' => $email, 'phone' => $phone]);
        }catch(\Throwable $e){
            Log::error('sendregisterMails dispatch failed', ['exception' => $e, 'email' => $email, 'phone' => $phone]);
        }
    }

    public function sendOfferGiven($offerData){
        try{
            if(!isset($offerData['type']))  $offerData['type'] = 'offerGiven';
            SendNotificationMailJob::dispatch($offerData)->onQueue('default');
            Log::info('sendOfferGiven dispatched successfully', ['offer' => $offerData]);
        }catch(\Throwable $e){
            Log::error('sendOfferGiven dispatch failed', ['exception' => $e, 'offer' => $offerData]);
        }
    }

    public function sendOfferStatus($offerData){
        try{
            $offerData['type'] = 'offerStatus';
            SendNotificationMailJob::dispatch($offerData)->onQueue('default');
            Log::info('sendOfferStatus dispatched successfully', ['offer' => $offerData]);
        }catch(\Throwable $e){
            Log::error('sendOfferStatus dispatch failed', ['exception' => $e, 'offer' => $offerData]);
        }
    }

    public function sendapproveMails($email){
        try{
            SendNotificationMailJob::dispatch([
                'email' => $email,
                'type' => 'activation',
            ])->onQueue('default');
            Log::info('sendapproveMails dispatched successfully', ['email' => $email]);
        }catch(\Throwable $e){
            Log::error('SendApproveMailJob dispatch failed', ['exception' => $e, 'email' => $email]);
        }
        
    }
    
    public function sendClientChanged($clientContactList,$clientData){
        try{
            SendNotificationMailJob::dispatch([
                'type' => 'clientUpdate',
                'client' => $clientData,
                'contacts' => $clientContactList,
            ])->onQueue('default');
            Log::info('sendclientUpdateMails dispatched successfully', ['client' => $clientData, 'contacts' => $clientContactList]);
        }catch(\Throwable $e){
            Log::error('sendclientUpdateMails dispatch failed', ['exception' => $e, 'client' => $clientData, 'contacts' => $clientContactList]);
        }
    }

    public function sendClientFileStatus($payload){
        try{
            SendNotificationMailJob::dispatch($payload)->onQueue('default');
            Log::info('sendclientFileStatusMails dispatched successfully', ['payload' => $payload]);
        }catch(\Throwable $e){
            Log::error('sendclientFileStatusMails dispatch failed', ['exception' => $e, 'payload' => $payload]);
        }
    }

    public function sendresetMail($email, $password){
        try{
            SendResetMailJob::dispatch($email, $password)->onQueue('default');
            Log::info('sendresetMail dispatched successfully', ['email' => $email, 'password' => $password]);
        }catch(\Throwable $e){
            Log::error('sendresetMail dispatch failed', ['exception' => $e, 'email' => $email, 'password' => $password]);
        }
        
    }

    public function sendinfoMail($email, $header, $body){
        try{
            SendInfoMailJob::dispatch($email, $header, $body)->onQueue('default');
            Log::info('sendinfoMail dispatched successfully', ['email' => $email, 'header' => $header, 'body' => $body]);
        }catch(\Throwable $e){
            Log::error('sendinfoMail dispatch failed', ['exception' => $e, 'email' => $email, 'header' => $header, 'body' => $body]);
        }
        
    }

   
}