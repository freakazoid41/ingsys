<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Classes\Justice\Justice;


class AiController extends Controller
{
    public function question(Request $request){
        set_time_limit(0);
        ini_set('max_execution_time', '0');


        $lib      = new Justice();
        $question = strip_tags($request->input('question'));
        
        $response = [
            'question' => $question,
            'answer'   => $lib->answer_question($question,session('email').'aisession')['answerHtml']
        ];

        return response()->json($response);
	}

    public function resetConversation(Request $request){
        $lib      = new Justice();
        $lib->reset_conversation(session('email').'aisession');
	}
}
