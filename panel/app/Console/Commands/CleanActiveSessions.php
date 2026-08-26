<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActiveSession;
use Carbon\Carbon;

class CleanActiveSessions extends Command
{
    protected $signature = 'active-sessions:clean
                            {--force-logout-hours=24 : Delete force_logout records older than this many hours}
                            {--stale-days=7 : Delete any active session untouched longer than this many days}';

    protected $description = 'Clean up stale active_sessions records (force_logout orphans + abandoned sessions)';

    public function handle(): int
    {
        $forceLogoutCutoff = Carbon::now()->subHours((int) $this->option('force-logout-hours'));
        $staleCutoff = Carbon::now()->subDays((int) $this->option('stale-days'));

        $forceLogoutDeleted = ActiveSession::where('force_logout', true)
            ->where('force_logout_at', '<', $forceLogoutCutoff)
            ->delete();

        $staleDeleted = ActiveSession::where('last_seen', '<', $staleCutoff)
            ->delete();

        $this->info("Cleaned up {$forceLogoutDeleted} force_logout records and {$staleDeleted} stale sessions.");

        return Command::SUCCESS;
    }
}
