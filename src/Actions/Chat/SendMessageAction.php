<?php
namespace RahatulRabbi\TalkBridge\Actions\Chat;

use Illuminate\Database\Eloquent\Model;
use RahatulRabbi\TalkBridge\Models\Message;
use RahatulRabbi\TalkBridge\Repositories\Chat\MessageRepository;

class SendMessageAction
{
    public function __construct(protected MessageRepository $messageRepo) {}

    public function execute(Model $user, array $data)
    {
        return $this->messageRepo->storeMessage($user, $data);
    }

    public function update(Model $user, array $data, Message $message)
    {
        return $this->messageRepo->updateMessage($user, $data, $message);
    }
}
