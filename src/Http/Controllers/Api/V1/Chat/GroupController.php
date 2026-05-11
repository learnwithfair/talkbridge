<?php
namespace RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use RahatulRabbi\TalkBridge\Http\Requests\Chat\CreateInviteRequest;
use RahatulRabbi\TalkBridge\Http\Requests\Chat\ManageGroupAdminRequest;
use RahatulRabbi\TalkBridge\Http\Requests\Chat\UpdateGroupInfoRequest;
use RahatulRabbi\TalkBridge\Services\ChatService;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;
class GroupController extends Controller {
    use ApiResponse;
    public function __construct(protected ChatService $chatService) {}
    public function addMembers(ManageGroupAdminRequest $request, int $conversationId) {
        return $this->success($this->chatService->addMembers(Auth::user(), $conversationId, $request->member_ids), 'Members added.');
    }
    public function acceptInvite(Request $request, string $token) {
        return $this->success($this->chatService->acceptInvite($request->user(), $token), 'Joined the group.');
    }
    public function regenerateInvite(CreateInviteRequest $request, int $conversationId) {
        return $this->success($this->chatService->regenerateInvite($request->user(), $request->validated(), $conversationId), 'Invite regenerated.');
    }
    public function getMembers(Request $request, int $conversationId) {
        return $this->success($this->chatService->getMembers($request->user(), $conversationId), 'Members fetched.');
    }
    public function removeMember(ManageGroupAdminRequest $request, int $conversationId) {
        return $this->success($this->chatService->removeMember(Auth::user(), $conversationId, $request->member_ids), 'Members removed.');
    }
    public function addAdmins(ManageGroupAdminRequest $request, int $conversationId) {
        return $this->success($this->chatService->addGroupAdmins(Auth::user(), $conversationId, $request->member_ids), 'Admins added.');
    }
    public function removeAdmins(ManageGroupAdminRequest $request, int $conversationId) {
        return $this->success($this->chatService->removeGroupAdmins(Auth::user(), $conversationId, $request->member_ids), 'Admins removed.');
    }
    public function muteToggleGroup(Request $request, int $conversationId) {
        $request->validate(['minutes'=>'nullable|integer']);
        $result = $this->chatService->muteGroup(Auth::user(), $conversationId, $request->minutes ?? 0);
        return $this->success(null, $result ? 'Group muted.' : 'Group unmuted.');
    }
    public function leaveGroup(int $conversationId) {
        $this->chatService->leaveGroup(Auth::user(), $conversationId);
        return $this->success(null, 'Left the group.');
    }
    public function deleteGroup(int $conversationId) {
        $this->chatService->deleteGroup(Auth::user(), $conversationId);
        return $this->success(null, 'Group deleted.');
    }
    public function update(UpdateGroupInfoRequest $request, int $conversation) {
        return $this->success($this->chatService->updateGroupInfo(Auth::user(), $conversation, $request->validated()), 'Group updated.');
    }
}
