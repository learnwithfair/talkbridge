<?php
namespace RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use RahatulRabbi\TalkBridge\Http\Requests\Chat\DeleteMessageRequest;
use RahatulRabbi\TalkBridge\Http\Requests\Chat\SendMessageRequest;
use RahatulRabbi\TalkBridge\Models\Message;
use RahatulRabbi\TalkBridge\Services\ChatService;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;
class MessageController extends Controller {
    use ApiResponse;
    public function __construct(protected ChatService $chatService) {}
    public function show(Request $request, int $message) {
        $perPage = $request->query('per_page', config('talkbridge.pagination.messages', 20));
        return $this->success($this->chatService->getMessages(Auth::user(), $message, $request->query('q'), $perPage), 'Messages fetched.', 200, true);
    }
    public function getAllPinnedMessages(Request $request, int $conversation) {
        $perPage = (int) $request->get('per_page', config('talkbridge.pagination.pinned', 40));
        return $this->success($this->chatService->pinnedMessages($request->user(), $conversation, $request->query('q'), $perPage), 'Pinned messages fetched.', 200, true);
    }
    public function store(SendMessageRequest $request) {
        return $this->success($this->chatService->sendMessage(Auth::user(), $request->validated()), 'Message sent.', 201);
    }
    public function update(SendMessageRequest $request, Message $message) {
        return $this->success($this->chatService->updateMessage(Auth::user(), $request->validated(), $message), 'Message updated.');
    }
    public function pinToggleMessage(Request $request, Message $message) {
        $result = $this->chatService->pinToggleMessage($request->user(), $message);
        return $this->success($result, $result['message']->is_pinned ? 'Message pinned.' : 'Message unpinned.');
    }
    public function deleteForMe(DeleteMessageRequest $request) {
        return $this->success($this->chatService->deleteForMe(Auth::user(), $request->validated()), 'Messages deleted for you.');
    }
    public function deleteForEveryone(DeleteMessageRequest $request) {
        return $this->success($this->chatService->deleteForEveryone(Auth::user(), $request->validated()), 'Messages deleted for everyone.');
    }
    public function markAsSeen(int $conversationId) {
        $this->chatService->markConversationAsRead(Auth::user(), $conversationId);
        return $this->success(null, 'Marked as seen.');
    }
    public function markSeen(Request $request) {
        $request->validate(['conversation_id'=>'required|integer|exists:conversations,id','message_ids'=>'required|array','message_ids.*'=>'integer|exists:messages,id']);
        return $this->success($this->chatService->markMessagesAsRead(Auth::user(), $request->all()), 'Messages marked as seen.');
    }
    public function markAsDelivered(int $conversationId) {
        $this->chatService->markDelivered(Auth::user(), $conversationId);
        return $this->success(null, 'Marked as delivered.');
    }
    public function forward(Request $request, Message $message) {
        $data    = $request->validate(['conversation_ids'=>['required','array','min:1'],'conversation_ids.*'=>['integer','exists:conversations,id']]);
        $results = [];
        foreach ($data['conversation_ids'] as $cid) {
            $results[] = $this->chatService->sendMessage($request->user(), [
                'conversation_id'=>$cid, 'message'=>$message->message,
                'message_type'=>$message->message_type, 'forward_to_message_id'=>$message->id,
            ]);
        }
        return $this->success($results, 'Message forwarded.', 201);
    }
}
