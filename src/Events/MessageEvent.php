<?php
namespace RahatulRabbi\TalkBridge\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class MessageEvent implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public string $type,
        public int    $conversationId,
        public array  $payload
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('conversation.' . $this->conversationId);
    }

    public function broadcastAs(): string { return 'MessageEvent'; }

    public function broadcastWith(): array
    {
        return ['type' => $this->type, 'payload' => $this->payload];
    }
}
