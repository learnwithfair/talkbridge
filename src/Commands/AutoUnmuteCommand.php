<?php
namespace RahatulRabbi\TalkBridge\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RahatulRabbi\TalkBridge\Jobs\UnmuteConversationJob;

class AutoUnmuteCommand extends Command
{
    protected $signature   = 'talkbridge:auto-unmute {--chunk=500}';
    protected $description = 'Queue auto-unmute jobs for expired muted conversations';

    public function handle(): int
    {
        $chunkSize  = (int) $this->option('chunk');
        $total      = 0;
        $connection = config('talkbridge.queue.connection');
        $queue      = config('talkbridge.queue.name');

        DB::table('conversation_participants')
            ->where('is_muted', true)
            ->whereNotNull('muted_until')
            ->where('muted_until', '<=', Carbon::now())
            ->select('id', 'user_id', 'conversation_id')
            ->chunkById($chunkSize, function ($rows) use (&$total, $connection, $queue) {
                foreach ($rows as $row) {
                    UnmuteConversationJob::dispatch($row->id, $row->user_id, $row->conversation_id)
                        ->onConnection($connection)->onQueue($queue);
                    $total++;
                }
            });

        if ($total > 0) $this->line("Queued {$total} unmute job(s).");
        return self::SUCCESS;
    }
}
