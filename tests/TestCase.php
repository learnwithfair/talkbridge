<?php
namespace RahatulRabbi\TalkBridge\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RahatulRabbi\TalkBridge\TalkBridgeServiceProvider;
use RahatulRabbi\TalkBridge\Models\Conversation;
use RahatulRabbi\TalkBridge\Models\ConversationParticipant;
use RahatulRabbi\TalkBridge\Models\Message;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TalkBridgeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('talkbridge.user_model', \App\Models\User::class);
        $app['config']->set('talkbridge.routing.middleware', ['api']);
        $app['config']->set('broadcast.default', 'log');
        $app['config']->set('talkbridge.push_notifications.provider', 'none');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function createUser(array $attributes = [])
    {
        return ($this->app['config']->get('talkbridge.user_model'))::factory()->create($attributes);
    }

    protected function createConversation($user, string $type = 'private'): Conversation
    {
        $conversation = Conversation::create(['type' => $type, 'name' => $type === 'group' ? 'Test Group' : null]);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'role' => 'member', 'is_active' => true]);
        return $conversation;
    }

    protected function createMessage(Conversation $conversation, $sender, string $text = 'Test message'): Message
    {
        return Message::create(['conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'message' => $text, 'message_type' => 'text']);
    }
}
