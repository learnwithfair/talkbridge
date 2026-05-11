<?php
namespace RahatulRabbi\TalkBridge\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use RahatulRabbi\TalkBridge\Models\Conversation;

class ConversationEvent implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public string       $action,
        public ?int         $targetUserId = null,
        public ?array       $meta = null
    ) {}

    public function broadcastOn(): PrivateChannel|PresenceChannel
    {
        return $this->targetUserId
            ? new PrivateChannel('user.' . $this->targetUserId)
            : new PresenceChannel('conversation.' . $this->conversation->id);
    }

    public function broadcastAs(): string { return 'ConversationEvent'; }

    public function broadcastWith(): array
    {
        return [
            'action'       => $this->action,
            'conversation' => [
                'id'   => $this->conversation->id,
                'name' => $this->conversation->name,
                'type' => $this->conversation->type,
                'avatar' => $this->conversation->groupSetting?->avatar
                    ? talkbridge_file_url($this->conversation->groupSetting->avatar)
                    : null,
                'meta'   => array_merge($this->conversation->meta ?? [], $this->meta ?? []),
            ],
        ];
    }
}
