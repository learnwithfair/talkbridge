<?php
namespace RahatulRabbi\TalkBridge\Http\Requests\Chat;
use RahatulRabbi\TalkBridge\Http\Requests\BaseRequest;
class DeleteMessageRequest extends BaseRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'message_id'    => 'nullable|integer|exists:messages,id',
            'message_ids'   => 'nullable|array',
            'message_ids.*' => 'integer|exists:messages,id',
        ];
    }
}
