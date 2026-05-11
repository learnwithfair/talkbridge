<?php

namespace RahatulRabbi\TalkBridge\Http\Resources\Chat;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $authId = Auth::id();
        $sender = $this->sender;

        if ($this->is_deleted_for_everyone) {
            return [
                'id'                      => $this->id,
                'conversation_id'         => $this->conversation_id,
                'sender'                  => $sender ? [
                    'id'   => $sender->id,
                    'name' => talkbridge_user_name($sender),
                ] : null,
                'is_deleted_for_everyone' => true,
                'message'                 => $this->message,
                'message_type'            => 'system',
                'reply_to_message_id'     => null,
                'forward_to_message_id'   => null,
                'attachments'             => [],
                'reactions'               => ['reactions' => [], 'total' => 0],
                'statuses'                => [],
                'reply'                   => null,
                'forward'                 => null,
                'is_pinned'               => false,
                'is_mine'                 => $this->sender_id === $authId,
                'edited_at'               => null,
                'created_at'              => $this->created_at?->toDateTimeString(),
                'updated_at'              => $this->updated_at?->toDateTimeString(),
            ];
        }

        $grouped = $this->reactions
            ->groupBy('reaction')
            ->map(fn($r) => $r->count())
            ->toArray();

        return [
            'id'                      => $this->id,
            'conversation_id'         => $this->conversation_id,
            'sender'                  => $sender ? [
                'id'     => $sender->id,
                'name'   => talkbridge_user_name($sender),
                'avatar' => talkbridge_user_avatar($sender),
            ] : null,
            'is_deleted_for_everyone' => false,
            'message'                 => $this->is_restricted ? null : $this->message,
            'message_type'            => $this->message_type,
            'reply_to_message_id'     => $this->reply_to_message_id,
            'forward_to_message_id'   => $this->forward_to_message_id,
            'attachments'             => MessageAttachmentResource::collection($this->attachments),
            'reactions'               => [
                'reactions' => $grouped,
                'total'     => array_sum($grouped),
            ],
            'statuses' => $this->statuses
                ->where('user_id', '!=', $authId)
                ->map(fn($s) => [
                    'user_id'    => $s->user_id,
                    'name'       => talkbridge_user_name($s->user),
                    'avatar'     => talkbridge_user_avatar($s->user),
                    'status'     => $s->status,
                    'created_at' => $s->created_at?->toDateTimeString(),
                ])->values(),
            'reply' => $this->replyTo ? [
                'id'      => $this->replyTo->id,
                'sender'  => $this->replyTo->sender ? [
                    'id'   => $this->replyTo->sender->id,
                    'name' => talkbridge_user_name($this->replyTo->sender),
                ] : null,
                'message' => $this->replyTo->message,
                'type'    => $this->replyTo->message_type,
            ] : null,
            'forward' => $this->forwardedFrom ? [
                'id'     => $this->forwardedFrom->id,
                'sender' => $this->forwardedFrom->sender ? [
                    'id'   => $this->forwardedFrom->sender->id,
                    'name' => ($this->forwardedFrom->conversation?->type === 'group')
                        ? ($this->forwardedFrom->conversation->name ?? talkbridge_user_name($this->forwardedFrom->sender))
                        : talkbridge_user_name($this->forwardedFrom->sender),
                ] : null,
                'message' => $this->forwardedFrom->message,
                'type'    => $this->forwardedFrom->message_type,
            ] : null,
            'is_pinned'  => $this->is_pinned,
            'is_mine'    => $this->sender_id === $authId,
            'edited_at'  => $this->edited_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
