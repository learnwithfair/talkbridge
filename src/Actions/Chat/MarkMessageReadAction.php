<?php
namespace RahatulRabbi\TalkBridge\Actions\Chat;

use Illuminate\Database\Eloquent\Model;
use RahatulRabbi\TalkBridge\Events\MessageEvent;
use RahatulRabbi\TalkBridge\Models\Conversation;
use RahatulRabbi\TalkBridge\Models\ConversationParticipant;
use RahatulRabbi\TalkBridge\Models\Message;
use RahatulRabbi\TalkBridge\Models\MessageStatus;

class MarkMessageReadAction
{
    public function execute(Model $user, int $conversationId)
    {
        $conversation = Conversation::find($conversationId);
        if (! $conversation) return null;

        $lastMessage = $conversation->messages()->latest('id')->first();
        if (! $lastMessage) return null;

        ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->update(['last_read_message_id' => $lastMessage->id]);

        foreach ($conversation->messages as $msg) {
            $msg->statuses()->updateOrCreate(['user_id' => $user->id], ['status' => 'seen']);
        }

        broadcast(new MessageEvent('seen', $conversationId, [
            'user_id'    => $user->id,
            'message_id' => $lastMessage->id,
            'user'       => $user,
            'created_at' => now(),
        ]))->toOthers();

        return $lastMessage;
    }

    public function markSeen(Model $user, array $data): array
    {
        $conversationId = (int) $data['conversation_id'];
        $messageIds     = array_unique($data['message_ids'] ?? []);
        $userId         = $user->id;

        if (empty($messageIds)) return ['seen_count' => 0, 'message_ids' => []];

        $messages = Message::where('conversation_id', $conversationId)
            ->whereIn('id', $messageIds)
            ->where('sender_id', '!=', $userId)
            ->select('id')->get();

        if ($messages->isEmpty()) return ['seen_count' => 0, 'message_ids' => []];

        $messageIds = $messages->pluck('id')->toArray();
        $now        = now();

        MessageStatus::upsert(
            collect($messageIds)->map(fn($id) => [
                'message_id' => $id,
                'user_id'    => $userId,
                'status'     => 'seen',
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray(),
            ['message_id', 'user_id'],
            ['status', 'updated_at']
        );

        $lastReadId = max($messageIds);

        ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_message_id' => $lastReadId]);

        broadcast(new MessageEvent('seen', $conversationId, [
            'user_id'    => $user->id,
            'message_id' => $lastReadId,
            'user'       => $user,
            'created_at' => now(),
        ]))->toOthers();

        return [
            'seen_count'           => count($messageIds),
            'message_ids'          => $messageIds,
            'last_read_message_id' => $lastReadId,
        ];
    }
}
