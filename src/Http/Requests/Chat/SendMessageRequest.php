<?php
namespace RahatulRabbi\TalkBridge\Http\Requests\Chat;
use RahatulRabbi\TalkBridge\Http\Requests\BaseRequest;
class SendMessageRequest extends BaseRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'conversation_id'     => 'nullable|exists:conversations,id',
            'receiver_id'         => 'nullable|exists:users,id',
            'message'             => 'nullable|string',
            'message_type'        => 'nullable|in:text,image,video,audio,file,multiple,system',
            'reply_to_message_id' => 'nullable|exists:messages,id',
            'attachments'         => 'nullable|array',
            'attachments.*.path'  => 'required|file',
        ];
    }
}
