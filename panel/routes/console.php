<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Classes\Justice\Justice;


Schedule::command('currency:cron')->hourly();


Artisan::command('ai:test {--chat : Enable conversation mode} {--session= : Session ID for conversation} {--reset : Reset conversation for the given session} {question? : The question to ask}', function () {
    $lib = new Justice();

    $question = $this->argument('question');
    $chatMode = $this->option('chat');
    $sessionId = $this->option('session');
    $reset = $this->option('reset');
    
    if (!$question) {
        $question = <<<'Q'
        bana Volkswagen golf ve mustang  hakkında bilgi ver
        Q;
    }
    #$lib->insert_documents();
    if ($reset && $sessionId) {
        $lib->reset_conversation($sessionId);
        $this->info("Conversation reset for session: $sessionId");
        return;
    }

    if ($chatMode && !$sessionId) {
        $this->error('Session ID is required when using chat mode. Use --session=your_session_id');
        return;
    }

    if ($chatMode) {
        $this->info("Chat mode enabled with session: $sessionId");
        print_r($lib->answer_question($question, $sessionId));
    } else {
        print_r($lib->answer_question($question));
    }
})->describe('AI question answering with optional conversation mode');