<?php
namespace RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use RahatulRabbi\TalkBridge\Services\ChatService;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;
class ConversationController extends Controller {
    use ApiResponse;
    public function __construct(protected ChatService $chatService) {}
    public function index(Request $request) {
        $perPage = (int) $request->get('per_page', config('talkbridge.pagination.conversations', 30));
        return $this->success($this->chatService->listConversations(Auth::user(), $perPage, $request->query('q')), 'Conversations fetched.', 200, true);
    }
    public function mediaLibrary(Request $request, int $conversationId) {
        $perPage = (int) $request->get('per_page', config('talkbridge.pagination.media', 30));
        return $this->success($this->chatService->mediaLibrary($request->user(), $conversationId, $perPage), 'Media library fetched.');
    }
    public function startPrivateConversation(Request $request) {
        return $this->success($this->chatService->startConversation(Auth::user(), $request->receiver_id), 'Conversation created.', 201);
    }
    public function store(Request $request) {
        return $this->success($this->chatService->createGroup(Auth::user(), $request->all()), 'Group created.', 201);
    }
    public function destroy(Request $request, int $conversation) {
        $this->chatService->deleteConversationForUser(Auth::id(), $conversation);
        return $this->success(null, 'Conversation removed.');
    }
}
