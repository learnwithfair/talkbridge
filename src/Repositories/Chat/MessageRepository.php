<?php

namespace RahatulRabbi\TalkBridge\Repositories\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use RahatulRabbi\TalkBridge\Events\ConversationEvent;
use RahatulRabbi\TalkBridge\Events\MessageEvent;
use RahatulRabbi\TalkBridge\Http\Resources\Chat\ConversationResource;
use RahatulRabbi\TalkBridge\Http\Resources\Chat\MediaLibraryResource;
use RahatulRabbi\TalkBridge\Http\Resources\Chat\MessageResource;
use RahatulRabbi\TalkBridge\Jobs\SendPushNotificationJob;
use RahatulRabbi\TalkBridge\Models\Conversation;
use RahatulRabbi\TalkBridge\Models\ConversationParticipant;
use RahatulRabbi\TalkBridge\Models\Message;
use RahatulRabbi\TalkBridge\Models\MessageAttachment;
use RahatulRabbi\TalkBridge\Models\MessageStatus;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;

class MessageRepository
{
    use ApiResponse;

    protected function withRelations(): array
    {
        return [
            'sender:id,name',
            'reactions',
            'attachments',
            'statuses',
            'replyTo.sender:id,name',
            'forwardedFrom.sender:id,name',
            'forwardedFrom.conversation:id,name,type',
        ];
    }

    public function getByConversation(Model $user, int $conversationId, ?string $query = null, int $perPage = 20)
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)->active()->firstOrFail();

        $messages = Message::where('conversation_id', $conversationId)
            ->when($participant->last_deleted_message_id, fn($q) => $q->where('id', '>', $participant->last_deleted_message_id))
            ->whereDoesntHave('deletions', fn($q) => $q->where('user_id', $user->id))
            ->when($query, fn($q) => $q->where('message', 'like', "%{$query}%"))
            ->with($this->withRelations())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return MessageResource::collection($messages);
    }

    public function getPinnedByConversation(Model $user, int $conversationId, ?string $query = null, int $perPage = 40)
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)->active()->firstOrFail();

        $messages = Message::where('conversation_id', $conversationId)
            ->pinned()
            ->when($participant->last_deleted_message_id, fn($q) => $q->where('id', '>', $participant->last_deleted_message_id))
            ->whereDoesntHave('deletions', fn($q) => $q->where('user_id', $user->id))
            ->when($query, fn($q) => $q->where('message', 'like', "%{$query}%"))
            ->with($this->withRelations())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return MessageResource::collection($messages);
    }

    public function find(int $messageId): ?Message
    {
        return Message::with(['sender', 'reactions', 'attachments'])->find($messageId);
    }

    public function mediaLibrary(Model $user, int $conversationId, int $perPage)
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)->active()->firstOrFail();

        $attachments = MessageAttachment::whereHas('message', function ($q) use ($conversationId, $participant, $user) {
            $q->where('conversation_id', $conversationId)
              ->when($participant->last_deleted_message_id, fn($q) => $q->where('id', '>', $participant->last_deleted_message_id))
              ->whereDoesntHave('deletions', fn($q) => $q->where('user_id', $user->id));
        })->latest()->paginate($perPage);

        $links = $this->extractLinks($conversationId, $participant->last_deleted_message_id, $user->id);

        return new MediaLibraryResource([
            'media'      => $attachments->whereIn('type', ['image', 'video'])->values(),
            'audio'      => $attachments->where('type', 'audio')->values(),
            'files'      => $attachments->whereNotIn('type', ['image', 'video', 'audio'])->values(),
            'links'      => $links,
            'pagination' => [
                'current_page' => $attachments->currentPage(),
                'last_page'    => $attachments->lastPage(),
                'per_page'     => $attachments->perPage(),
                'total'        => $attachments->total(),
            ],
        ]);
    }

    public function storeMessage(Model $user, array $data)
    {
        if (empty($data['conversation_id']) && ! empty($data['receiver_id'])) {
            $data['conversation_id'] = app(\RahatulRabbi\TalkBridge\Services\ChatService::class)
                ->startConversation($user, $data['receiver_id'])->id;
        }

        $participant = ConversationParticipant::where('conversation_id', $data['conversation_id'])
            ->where('user_id', $user->id)->active()->first();

        if (! $participant) {
            throw new HttpResponseException($this->error(null, 'You are not a member of this conversation.', 403));
        }

        $conversation = Conversation::findOrFail($data['conversation_id']);

        if ($conversation->type === 'private' && $conversation->otherParticipant($user)?->hasBlocked($user)) {
            throw new HttpResponseException($this->error(null, 'You cannot send messages to this user.', 403));
        }

        if (! $conversation->canUserSendMessage($participant)) {
            throw new HttpResponseException($this->error(null, 'You are not allowed to send messages.', 403));
        }

        $hadMessagesBefore = Message::where('conversation_id', $conversation->id)->lockForUpdate()->exists();

        $message = Message::create([
            'conversation_id'       => $data['conversation_id'],
            'sender_id'             => $user->id,
            'receiver_id'           => $data['receiver_id'] ?? null,
            'message'               => $data['message'] ?? null,
            'message_type'          => $data['message_type'] ?? 'text',
            'reply_to_message_id'   => $data['reply_to_message_id'] ?? null,
            'forward_to_message_id' => $data['forward_to_message_id'] ?? null,
            'is_restricted'         => ! empty($data['receiver_id']) &&
                $user->restrictedByUsers()->where('users.id', $data['receiver_id'])->exists(),
        ]);

        if (! empty($data['forward_to_message_id'])) {
            $this->cloneAttachments(Message::findOrFail($data['forward_to_message_id']), $message);
        } elseif (! empty($data['attachments'])) {
            foreach ($data['attachments'] as $file) {
                /** @var \Illuminate\Http\UploadedFile $uploaded */
                $uploaded     = $file['path'];
                $originalName = $uploaded->getClientOriginalName();
                $fileSize     = $uploaded->getSize(); // read BEFORE storeAs moves the temp file

                $mediaPath = talkbridge_upload_file(
                    $uploaded,
                    config('talkbridge.uploads.message_path', 'uploads/messages'),
                    (string) Str::uuid()
                );

                $message->attachments()->create([
                    'path' => $mediaPath,                          // relative storage path
                    'type' => talkbridge_file_type($originalName), // detect from original name
                    'name' => $originalName,
                    'size' => $fileSize ?: null,
                ]);
            }
        }

        $participant->update(['last_read_message_id' => $message->id]);

        $deletedParticipants = ConversationParticipant::where('conversation_id', $conversation->id)
            ->whereNotNull('deleted_at')->get(['id', 'user_id']);

        if ($deletedParticipants->isNotEmpty()) {
            ConversationParticipant::whereIn('id', $deletedParticipants->pluck('id'))
                ->update(['is_active' => true, 'deleted_at' => null]);
        }

        $participantIds = ConversationParticipant::where('conversation_id', $conversation->id)->active()->pluck('user_id');
        $now            = now();

        MessageStatus::insert($participantIds->map(fn($uid) => [
            'message_id' => $message->id,
            'user_id'    => $uid,
            'status'     => $uid === $user->id ? 'seen' : 'sent',
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray());

        $conversation->touch();

        $pushProvider = config('talkbridge.push_notifications.provider', 'none');
        if ($pushProvider !== 'none') {
            $this->dispatchPushNotification($conversation, $message, $user);
        }

        $message->load($this->withRelations());

        $userModel = config('talkbridge.user_model');

        if ($deletedParticipants->isNotEmpty()) {
            $conversation->fresh()->load(['participants.user', 'lastMessage.sender', 'lastMessage.attachments', 'creator:id,name', 'groupSetting', 'activeInvites']);
            foreach ($deletedParticipants as $p) {
                $targetUser           = $userModel::find($p->user_id);
                $conversationResource = (new ConversationResource($conversation))->forUser($targetUser)->toArray(request());
                event(new ConversationEvent($conversation, 'added', $p->user_id, $conversationResource));
            }
        }

        if ($conversation->type === 'private' && ! $hadMessagesBefore) {
            $receiver = $conversation->otherParticipant($user);
            if ($receiver) {
                $conversation->load(['participants.user', 'lastMessage.sender', 'lastMessage.attachments', 'creator:id,name', 'groupSetting', 'activeInvites']);
                $conversationResource = (new ConversationResource($conversation))->forUser($receiver)->toArray(request());
                event(new ConversationEvent($conversation, 'added', $receiver->id, $conversationResource));
            }
        }

        return new MessageResource($message);
    }

    public function updateMessage(Model $user, array $data, Message $message)
    {
        if ($message->sender_id !== $user->id) {
            throw new HttpResponseException($this->error(null, 'You cannot edit this message.', 403));
        }

        $data['edited_at'] = now();
        $message->update($data);
        $message->load($this->withRelations());

        return new MessageResource($message);
    }

    public function deleteMessagesForUser(int $userId, array $messageIds): string
    {
        Message::whereIn('id', $messageIds)->get()
            ->each(fn($m) => $m->deletions()->firstOrCreate(['user_id' => $userId]));

        return 'Messages deleted for you.';
    }

    public function deleteMessagesForEveryone(int $userId, array $messageIds): string
    {
        $messages = Message::whereIn('id', $messageIds)->get();

        foreach ($messages as $message) {
            if ($message->sender_id !== $userId) {
                throw new HttpResponseException($this->error(null, 'You can only delete your own messages.', 403));
            }

            $placeholder = config('talkbridge.messages.unsent_placeholder', 'Unsent');

            if ($message->is_deleted_for_everyone && $message->message === $placeholder) {
                $cid = $message->conversation_id;
                $mid = $message->id;
                $message->delete();
                broadcast(new MessageEvent('deleted_permanent', $cid, ['message_id' => $mid]));
                continue;
            }

            $message->update([
                'is_deleted_for_everyone' => true,
                'message'                 => $placeholder,
                'is_pinned'               => false,
            ]);

            talkbridge_delete_files($message->attachments->pluck('path')->toArray());
            $message->attachments()->delete();

            broadcast(new MessageEvent('deleted_for_everyone', $message->conversation_id, $message->toArray()));
            broadcast(new MessageEvent('unpinned', $message->conversation_id, $message->toArray()));
        }

        return 'Messages deleted for everyone.';
    }

    protected function cloneAttachments(Message $from, Message $to): void
    {
        if (! $from->relationLoaded('attachments')) {
            $from->load('attachments');
        }

        if ($from->attachments->isEmpty()) return;

        $rows = $from->attachments->map(fn($f) => [
            'message_id' => $to->id,
            'path'       => $f->path,
            'type'       => $f->type,
            'name'       => $f->name,
            'size'       => $f->size,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        MessageAttachment::insert($rows);
    }

    protected function dispatchPushNotification(Conversation $conversation, Message $message, Model $sender): void
    {
        $participants = ConversationParticipant::where('conversation_id', $conversation->id)
            ->active()->unmuted()->with('user.deviceTokens')->get();

        $connection = config('talkbridge.queue.connection');
        $queue      = config('talkbridge.queue.name');

        foreach ($participants as $participant) {
            if ($participant->user_id === $sender->id) continue;

            $tokens = $participant->user?->deviceTokens->pluck('token')->filter()->toArray();
            if (empty($tokens)) continue;

            $title = talkbridge_user_name($sender);
            $body  = $message->message_type === 'text' ? ($message->message ?: 'New message') : 'Sent an attachment';

            SendPushNotificationJob::dispatch(
                $tokens,
                $participant->user_id,
                $title,
                $body,
                [
                    'type'            => 'chat_message',
                    'conversation_id' => (string) $conversation->id,
                    'message_id'      => (string) $message->id,
                    'sender_id'       => (string) $sender->id,
                ],
                null,
                false
            )->onConnection($connection)->onQueue($queue)->afterCommit();
        }
    }

    protected function extractLinks(int $conversationId, ?int $lastDeletedId, int $userId): array
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->whereNotNull('message')
            ->whereIn('message_type', ['text', 'multiple'])
            ->when($lastDeletedId, fn($q) => $q->where('id', '>', $lastDeletedId))
            ->whereDoesntHave('deletions', fn($q) => $q->where('user_id', $userId))
            ->select('id', 'message', 'created_at')
            ->latest()->get();

        $links = [];

        foreach ($messages as $message) {
            preg_match_all('/https?:\/\/[^\s\)\]\}\>,"]+/i', $message->message, $matches);
            foreach (array_unique($matches[0] ?? []) as $url) {
                $links[] = ['message_id' => $message->id, 'url' => $url, 'created_at' => $message->created_at];
            }
        }

        return $links;
    }
}
