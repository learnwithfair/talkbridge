<?php

namespace RahatulRabbi\TalkBridge\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use RahatulRabbi\TalkBridge\Events\MessageEvent;
use RahatulRabbi\TalkBridge\Models\Message;
use RahatulRabbi\TalkBridge\Models\MessageReaction;
use RahatulRabbi\TalkBridge\Models\MessageStatus;
use RahatulRabbi\TalkBridge\Repositories\Chat\ConversationRepository;
use RahatulRabbi\TalkBridge\Repositories\Chat\MessageRepository;
use RahatulRabbi\TalkBridge\Actions\Chat\CreateConversationAction;
use RahatulRabbi\TalkBridge\Actions\Chat\SendMessageAction;
use RahatulRabbi\TalkBridge\Actions\Chat\MarkMessageReadAction;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;

class ChatService
{
    use ApiResponse;

    public function __construct(
        protected ConversationRepository  $conversationRepo,
        protected MessageRepository       $messageRepo,
        protected CreateConversationAction $createConversation,
        protected SendMessageAction        $sendMessageAction,
        protected MarkMessageReadAction    $markRead
    ) {}

    // -------------------------------------------------------------------------
    // Conversations
    // -------------------------------------------------------------------------

    public function listConversations(Model $user, int $perPage, ?string $query = null)
    {
        return $this->conversationRepo->listFor($user, $perPage, $query);
    }

    public function startConversation(Model $user, int $receiverId)
    {
        return $this->createConversation->execute($user, $receiverId);
    }

    public function createGroup(Model $user, array $data)
    {
        return $this->conversationRepo->createGroupConversation($data, $user->id);
    }

    public function deleteConversationForUser(int $userId, int $conversationId): bool
    {
        return $this->conversationRepo->deleteForUser($userId, $conversationId);
    }

    public function mediaLibrary(Model $user, int $conversationId, int $perPage = 30)
    {
        if (! $this->conversationRepo->canUserPermit($conversationId, $user->id)) {
            throw new HttpResponseException($this->error(null, 'You are not a member of this conversation.', 403));
        }
        return $this->messageRepo->mediaLibrary($user, $conversationId, $perPage);
    }

    // -------------------------------------------------------------------------
    // Messages
    // -------------------------------------------------------------------------

    public function getMessages(Model $user, int $conversationId, ?string $query = null, int $perPage = 20)
    {
        return $this->messageRepo->getByConversation($user, $conversationId, $query, $perPage);
    }

    public function pinnedMessages(Model $user, int $conversationId, ?string $query = null, int $perPage = 40)
    {
        return $this->messageRepo->getPinnedByConversation($user, $conversationId, $query, $perPage);
    }

    public function sendMessage(Model $user, array $data)
    {
        $message = $this->sendMessageAction->execute($user, $data);
        event(new MessageEvent('sent', $message->conversation_id, $message->resolve()));
        return $message;
    }

    public function updateMessage(Model $user, array $data, Message $message)
    {
        $updated = $this->sendMessageAction->update($user, $data, $message);
        event(new MessageEvent('updated', $message->conversation_id, $message->fresh()->toArray()));
        return $updated;
    }

    public function pinToggleMessage(Model $user, Message $message)
    {
        return $this->conversationRepo->pinToggleMessage($user, $message);
    }

    public function deleteForMe(Model $user, array $data): string
    {
        $ids = $data['message_ids'] ?? (isset($data['message_id']) ? [$data['message_id']] : []);
        if (empty($ids)) {
            throw new HttpResponseException($this->error(null, 'No messages provided.', 422));
        }
        return $this->messageRepo->deleteMessagesForUser($user->id, $ids);
    }

    public function deleteForEveryone(Model $user, array $data): string
    {
        $ids = $data['message_ids'] ?? (isset($data['message_id']) ? [$data['message_id']] : []);
        if (empty($ids)) {
            throw new HttpResponseException($this->error(null, 'No messages provided.', 422));
        }
        return $this->messageRepo->deleteMessagesForEveryone($user->id, $ids);
    }

    public function markConversationAsRead(Model $user, int $conversationId)
    {
        return $this->markRead->execute($user, $conversationId);
    }

    public function markMessagesAsRead(Model $user, array $data)
    {
        return $this->markRead->markSeen($user, $data);
    }

    public function markDelivered(Model $user, int $conversationId): void
    {
        MessageStatus::where('user_id', $user->id)
            ->whereHas('message', fn($q) => $q->where('conversation_id', $conversationId))
            ->where('status', 'sent')
            ->update(['status' => 'delivered']);

        broadcast(new MessageEvent('delivered', $conversationId, ['user_id' => $user->id]));
    }

    // -------------------------------------------------------------------------
    // Reactions
    // -------------------------------------------------------------------------

    public function toggleReaction(Model $user, int $messageId, string $reaction)
    {
        $message  = $this->messageRepo->find($messageId);
        if (! $message) {
            throw new HttpResponseException($this->error(null, 'Message not found.', 404));
        }

        $existing = $message->reactions()->where('user_id', $user->id)->first();
        if ($existing && $existing->reaction === $reaction) {
            $message->reactions()->where('user_id', $user->id)->delete();
        } else {
            $message->reactions()->updateOrCreate(
                ['user_id' => $user->id, 'message_id' => $messageId],
                ['reaction' => $reaction]
            );
        }

        $reactions = $message->reactions()->with('user')->get();
        broadcast(new MessageEvent('reaction', $message->conversation_id, [
            'message_id' => $message->id,
            'reactions'  => $reactions,
        ]));

        return $reactions;
    }

    public function listReactions(int $messageId): array
    {
        $avatarField = config('talkbridge.user_fields.avatar', 'avatar_path');
        $reactions   = MessageReaction::where('message_id', $messageId)->with(['user'])->get();

        $grouped = $reactions->groupBy('reaction')->map(fn($items) => [
            'count' => $items->count(),
            'users' => $items->map(fn($r) => [
                'user_id'    => $r->user_id,
                'name'       => talkbridge_user_name($r->user),
                'avatar'     => talkbridge_user_avatar($r->user),
                'created_at' => $r->created_at->toDateTimeString(),
            ])->values(),
        ]);

        return ['total_reactions' => $reactions->count(), 'grouped' => $grouped];
    }

    // -------------------------------------------------------------------------
    // Group management
    // -------------------------------------------------------------------------

    public function getMembers(Model $user, int $groupId)
    {
        return $this->conversationRepo->getMembers($user->id, $groupId);
    }

    public function addMembers(Model $user, int $groupId, array $memberIds)
    {
        return $this->conversationRepo->addMembers($user, $groupId, $memberIds);
    }

    public function removeMember(Model $user, int $groupId, array $memberIds)
    {
        return $this->conversationRepo->removeMember($user->id, $groupId, $memberIds);
    }

    public function addGroupAdmins(Model $actor, int $conversationId, array $userIds)
    {
        return $this->conversationRepo->addGroupAdmins($actor, $conversationId, $userIds);
    }

    public function removeGroupAdmins(Model $actor, int $conversationId, array $userIds)
    {
        return $this->conversationRepo->removeGroupAdmins($actor, $conversationId, $userIds);
    }

    public function muteGroup(Model $user, int $groupId, int $minutes = 0)
    {
        return $this->conversationRepo->muteGroup($user->id, $groupId, $minutes);
    }

    public function leaveGroup(Model $user, int $groupId)
    {
        return $this->conversationRepo->leaveGroup($user, $groupId);
    }

    public function updateGroupInfo(Model $user, int $groupId, array $data)
    {
        return $this->conversationRepo->updateGroupInfo($user->id, $groupId, $data);
    }

    public function deleteGroup(Model $user, int $groupId)
    {
        $this->conversationRepo->canGroupDeletePermit($user->id, $groupId);
        return $this->conversationRepo->deleteGroup($groupId);
    }

    public function acceptInvite(Model $user, string $token)
    {
        return $this->conversationRepo->acceptInvite($user, $token);
    }

    public function regenerateInvite(Model $user, array $data, int $groupId)
    {
        return $this->conversationRepo->regenerateInvite($user, $data, $groupId);
    }

    public function createDefault(int $conversationId)
    {
        return $this->conversationRepo->createDefault($conversationId);
    }

    // -------------------------------------------------------------------------
    // User blocking
    // -------------------------------------------------------------------------

    public function toggleBlock(Model $user, int $userId)
    {
        return $this->conversationRepo->toggleBlock($user, $userId);
    }

    public function toggleRestrict(Model $user, int $userId)
    {
        return $this->conversationRepo->toggleRestrict($user, $userId);
    }
}
