<?php
namespace RahatulRabbi\TalkBridge\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RahatulRabbi\TalkBridge\Events\ConversationEvent;
use RahatulRabbi\TalkBridge\Models\Conversation;

class UnmuteConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        protected int $participantId,
        protected int $userId,
        protected int $conversationId
    ) {}

    public function handle(): void
    {
        try {
            $updated = DB::table('conversation_participants')
                ->where('id', $this->participantId)
                ->where('is_muted', true)
                ->update(['is_muted' => false, 'muted_until' => null, 'updated_at' => Carbon::now()]);

            if ($updated) {
                $conversation = Conversation::find($this->conversationId);
                if ($conversation) {
                    broadcast(new ConversationEvent(
                        $conversation,
                        'unmuted',
                        $this->userId,
                        ['participant_id' => $this->participantId, 'is_muted' => false]
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::error('TalkBridge UnmuteConversationJob failed', [
                'participant_id'  => $this->participantId,
                'user_id'         => $this->userId,
                'conversation_id' => $this->conversationId,
                'error'           => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TalkBridge UnmuteConversationJob permanently failed', [
            'participant_id'  => $this->participantId,
            'user_id'         => $this->userId,
            'conversation_id' => $this->conversationId,
            'error'           => $exception->getMessage(),
        ]);
    }
}
