<?php
namespace RahatulRabbi\TalkBridge\Actions\Chat;

use Illuminate\Database\Eloquent\Model;
use RahatulRabbi\TalkBridge\Http\Resources\Chat\ConversationResource;
use RahatulRabbi\TalkBridge\Repositories\Chat\ConversationRepository;

class CreateConversationAction
{
    public function __construct(protected ConversationRepository $conversationRepo) {}

    public function execute(Model $user, int $receiverId): ConversationResource
    {
        $conversation = $this->conversationRepo->findPrivateBetween($user->id, $receiverId);

        if ($conversation) {
            $conversation->load([
                'participants' => fn($q) => $q->where('is_active', true)->with('user'),
                'lastMessage.sender',
                'groupSetting',
            ]);
            $conversation->setRelation('unread_count', 0);
            return new ConversationResource($conversation);
        }

        return $this->conversationRepo->createPrivateConversation($user->id, $receiverId);
    }
}
