<?php

namespace RahatulRabbi\TalkBridge\Http\Resources\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    protected ?Model $forUser = null;

    public function forUser(Model $user): static
    {
        $this->forUser = $user;
        return $this;
    }

    public function toArray($request): array
    {
        $authUser    = $this->forUser ?? $request?->user();
        $participant = $authUser ? $this->participants->firstWhere('user_id', $authUser->id) : null;

        $receiver      = null;
        $isBlocked     = false;
        $isOnline      = false;
        $blockedByMe   = false;
        $blockedByThem = false;

        if ($this->type === 'private' && $authUser) {
            $receiver = $this->otherParticipant($authUser);

            if ($receiver) {
                $blockedByMe   = method_exists($authUser, 'hasBlocked')   ? (bool) $authUser->hasBlocked($receiver)   : false;
                $blockedByThem = method_exists($receiver, 'hasBlocked')   ? (bool) $receiver->hasBlocked($authUser)   : false;
                $isBlocked     = $blockedByMe || $blockedByThem;
                $isOnline      = talkbridge_user_online($receiver);
            }
        }

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'type'          => $this->type,

            'last_message'  => $this->whenLoaded('lastMessage', function () {
                $sender = $this->lastMessage?->sender;
                return $this->lastMessage ? [
                    'id'          => $this->lastMessage->id,
                    'message'     => $this->lastMessage->message,
                    'attachments' => MessageAttachmentResource::collection(
                        $this->lastMessage->relationLoaded('attachments')
                            ? $this->lastMessage->attachments
                            : collect()
                    ),
                    'sender' => $sender ? [
                        'id'        => $sender->id,
                        'name'      => talkbridge_user_name($sender),
                        'avatar'    => talkbridge_user_avatar($sender),
                        'is_online' => talkbridge_user_online($sender),
                        'last_seen' => talkbridge_user_last_seen($sender),
                    ] : null,
                    'created_at' => $this->lastMessage->created_at?->toDateTimeString(),
                ] : null;
            }),

            'participants'  => $this->participants->take(3)->map(fn($p) => [
                'id'        => $p->user_id,
                'name'      => talkbridge_user_name($p->user),
                'role'      => $p->role,
                'avatar'    => talkbridge_user_avatar($p->user),
                'is_muted'  => $p->is_muted,
                'is_online' => talkbridge_user_online($p->user),
            ]),

            'receiver'      => $receiver ? [
                'id'        => $receiver->id,
                'name'      => talkbridge_user_name($receiver),
                'avatar'    => talkbridge_user_avatar($receiver),
                'is_online' => $isOnline,
                'last_seen' => talkbridge_user_last_seen($receiver),
            ] : null,

            'is_online'        => $isOnline,
            'is_blocked'       => $isBlocked,
            'blocked'          => ['by_me' => $blockedByMe, 'by_them' => $blockedByThem],
            'unread_count'     => $this->unread_count ?? 0,
            'is_admin'         => in_array($participant?->role, ['admin', 'super_admin']),
            'role'             => $participant?->role,
            'is_muted'         => $participant?->is_muted,
            'group_setting'    => $this->groupSetting ? array_merge(
                $this->groupSetting->toArray(),
                [
                    'avatar' => $this->groupSetting->avatar
                        ? talkbridge_file_url($this->groupSetting->avatar)
                        : null,
                ]
            ) : null,
            'can_send_message' => $this->canUserSendMessage($participant),
            'invite_link'      => $this->inviteLink
                ? rtrim(config('talkbridge.invite_url', config('app.url') . '/api/v1/accept-invite'), '/') . '/' . $this->inviteLink->token
                : null,
            'created_by'       => talkbridge_user_name($this->creator),
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),
        ];
    }
}
