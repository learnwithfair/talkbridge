<?php
namespace RahatulRabbi\TalkBridge\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RahatulRabbi\TalkBridge\Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_message(): void
    {
        $user         = $this->createUser();
        $conversation = $this->createConversation($user);

        $response = $this->actingAs($user)->postJson('/api/v1/messages', [
            'conversation_id' => $conversation->id,
            'message'         => 'Hello from TalkBridge test',
            'message_type'    => 'text',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.message', 'Hello from TalkBridge test');
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'message' => 'Hello from TalkBridge test']);
    }

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $this->postJson('/api/v1/messages', ['message' => 'Hello'])->assertStatus(401);
    }

    public function test_user_can_delete_message_for_themselves(): void
    {
        $user         = $this->createUser();
        $conversation = $this->createConversation($user);
        $message      = $this->createMessage($conversation, $user);

        $this->actingAs($user)->deleteJson('/api/v1/messages/delete-for-me', ['message_ids' => [$message->id]])->assertStatus(200);
    }

    public function test_user_can_delete_own_message_for_everyone(): void
    {
        $user         = $this->createUser();
        $conversation = $this->createConversation($user);
        $message      = $this->createMessage($conversation, $user);

        $this->actingAs($user)->deleteJson('/api/v1/messages/delete-for-everyone', ['message_ids' => [$message->id]])->assertStatus(200);
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'is_deleted_for_everyone' => true]);
    }
}
