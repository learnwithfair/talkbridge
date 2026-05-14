# TalkBridge

Real-time chat package for Laravel 11, 12 and 13.

Private and group conversations, message reactions, file attachments, message status
(sent / delivered / seen), typing indicators, user blocking, group management,
FCM and Web Push notifications, WebSocket broadcasting via Reverb or Pusher.

**Zero manual steps. Everything is configured automatically on install and removed on uninstall.**

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.2 or higher |
| Laravel | 11.x, 12.x, or 13.x |
| Laravel Sanctum | 4.x or 5.x |

All optional packages (Reverb, Pusher, Firebase, Web Push) are installed
automatically based on your choices during `talkbridge:install`.

---

## Quick Start

```bash
composer require rahatulrabbi/talkbridge
php artisan talkbridge:install
```

The wizard asks two questions, then does everything else automatically:

```
[2] Select broadcasting driver
    Which broadcasting driver do you want to use?
  > reverb — Reverb (self-hosted WebSocket, recommended)
    pusher — Pusher Channels (cloud, requires credentials)
    ably   — Ably (cloud, requires API key)
    log    — Log driver (testing / local only)
    null   — Null driver (broadcasting disabled)

[5] Select push notification provider
    Which push notification provider do you want?
  > none — Disabled (no push notifications)
    fcm  — Firebase Cloud Messaging (Android + iOS)
    web  — Browser Web Push via VAPID (desktop browsers)
    both — FCM + Web Push (mobile and browser)
```

After answering, TalkBridge:

- Installs the broadcaster package (`composer require laravel/reverb` etc.)
- Installs the push notification package (`composer require kreait/laravel-firebase` etc.)
- Runs `composer dump-autoload` after each install
- Publishes `config/talkbridge.php`
- Publishes all database migrations
- Writes all `.env` variables
- Injects `HasTalkBridgeFeatures` into your `App\Models\User`
- Adds `last_seen_at` to `$fillable` (if your model uses `$fillable`)
- Registers middleware alias, scheduler, broadcast channels, API routes — all via ServiceProvider

---

## After Install

```bash
# 1. Review and adjust user field mapping if your columns differ
#    See: Configuration section below

# 2. Start WebSocket server (if using Reverb)
php artisan reverb:start --debug

# 3. Start queue worker
php artisan queue:work --queue=talkbridge
```

---

## Configuration

Open `config/talkbridge.php`. The most important section is `user_fields`.

### User field mapping

TalkBridge needs to know your column names. Change the values to match your database:

```php
'user_fields' => [
    'id'        => 'id',
    'name'      => 'name',           // single column — most common
    'avatar'    => 'avatar_path',    // your avatar column name
    'last_seen' => 'last_seen_at',   // your last_seen column name
    'is_active' => null,             // set to 'is_active' if you have this column
],
```

### Composite name columns

If your users table stores first and last name separately:

```php
'user_fields' => [
    'name' => ['first_name', 'last_name'],
    // or three parts:
    'name' => ['f_name', 'm_name', 'l_name'],
],
```

TalkBridge will automatically join them with a space everywhere — system messages,
presence channel payloads, reaction lists, conversation resources.

### Disable built-in routes

If you want to define routes yourself:

```php
'routing' => [
    'enabled' => false,
],
```

Copy `stubs/talkbridge/` to your routes folder and register manually in `bootstrap/app.php`.

### Change route prefix or middleware

```php
'routing' => [
    'enabled'    => true,
    'prefix'     => 'api/v2',
    'middleware' => ['api', 'auth:sanctum', 'talkbridge.last-seen'],
],
```

### Change upload disk

```php
'uploads' => [
    'disk'             => 's3',
    'message_path'     => 'chat/messages',
    'group_avatar_path'=> 'chat/groups/avatars',
],
```

### Group defaults

Control what settings new groups get:

```php
'group_defaults' => [
    'allow_members_to_send_messages'           => true,
    'allow_members_to_add_remove_participants' => false,
    'allow_members_to_change_group_info'       => false,
    'admins_must_approve_new_members'          => false,
    'allow_invite_users_via_link'              => true,
],
```

---

## Using TalkBridge in Your Own Code

Every feature is available as a service method. Inject `ChatService` anywhere
in your application.

### Inject the service

```php
use RahatulRabbi\TalkBridge\Services\ChatService;

class YourController extends Controller
{
    public function __construct(protected ChatService $chat) {}
}
```

Or use the Facade:

```php
use RahatulRabbi\TalkBridge\Facades\TalkBridge;
```

---

### Conversations

```php
// List conversations (paginated, supports search)
$conversations = $chat->listConversations($user, perPage: 30, query: 'search term');

// Start or get a private conversation
$conversation = $chat->startConversation($user, receiverId: 5);

// Create a group
$group = $chat->createGroup($user, [
    'name'         => 'Project Team',
    'participants' => [2, 3, 4],
    'group'        => ['description' => 'Our team', 'type' => 'private'],
]);

// Remove conversation from user's list (soft delete for that user only)
$chat->deleteConversationForUser($user->id, $conversationId);

// Media library for a conversation (images, video, audio, files, links)
$media = $chat->mediaLibrary($user, $conversationId, perPage: 30);
```

---

### Messages

```php
// Send a text message
$message = $chat->sendMessage($user, [
    'conversation_id' => 15,
    'message'         => 'Hello team!',
    'message_type'    => 'text',
]);

// Send with a file attachment
$message = $chat->sendMessage($user, [
    'conversation_id' => 15,
    'message_type'    => 'image',
    'attachments'     => [['path' => $request->file('image')]],
]);

// Reply to a message
$message = $chat->sendMessage($user, [
    'conversation_id'     => 15,
    'message'             => 'I agree',
    'reply_to_message_id' => 42,
]);

// Forward a message to multiple conversations
$chat->sendMessage($user, [
    'conversation_id'       => 20,
    'message'               => $original->message,
    'message_type'          => $original->message_type,
    'forward_to_message_id' => $original->id,
]);

// Edit a message
$updated = $chat->updateMessage($user, ['message' => 'Corrected text'], $message);

// Get messages (paginated, supports search)
$messages = $chat->getMessages($user, $conversationId, query: null, perPage: 20);

// Get pinned messages
$pinned = $chat->pinnedMessages($user, $conversationId);

// Pin or unpin
$chat->pinToggleMessage($user, $message);

// Delete for current user only
$chat->deleteForMe($user, ['message_ids' => [10, 11, 12]]);

// Unsend for everyone
$chat->deleteForEveryone($user, ['message_ids' => [10]]);

// Mark all messages as seen when opening a conversation
$chat->markConversationAsRead($user, $conversationId);

// Mark specific messages as seen (when conversation is already open)
$chat->markMessagesAsRead($user, [
    'conversation_id' => 15,
    'message_ids'     => [40, 41, 42],
]);

// Mark as delivered
$chat->markDelivered($user, $conversationId);
```

---

### Reactions

```php
// Toggle a reaction (adds if not present, removes if already reacted with same emoji)
$reactions = $chat->toggleReaction($user, $messageId, '❤️');

// List all reactions grouped by emoji
$reactions = $chat->listReactions($messageId);
// Returns:
// [
//   'total_reactions' => 5,
//   'grouped' => [
//     '❤️' => ['count' => 3, 'users' => [...]],
//     '👍' => ['count' => 2, 'users' => [...]],
//   ]
// ]
```

---

### Group Management

```php
// Add members
$chat->addMembers($user, $groupId, memberIds: [5, 6, 7]);

// Remove members
$chat->removeMember($user, $groupId, memberIds: [6]);

// Promote to admin
$chat->addGroupAdmins($user, $groupId, userIds: [5]);

// Demote admin
$chat->removeGroupAdmins($user, $groupId, userIds: [5]);

// Get all members
$members = $chat->getMembers($user, $groupId);

// Mute a group
$chat->muteGroup($user, $groupId, minutes: 60);   // mute for 60 minutes
$chat->muteGroup($user, $groupId, minutes: -1);   // mute forever
$chat->muteGroup($user, $groupId, minutes: 0);    // unmute

// Leave
$chat->leaveGroup($user, $groupId);

// Delete (super_admin only)
$chat->deleteGroup($user, $groupId);

// Update group name, description, avatar, settings
$chat->updateGroupInfo($user, $groupId, [
    'name'  => 'New Name',
    'group' => [
        'description'                    => 'Updated description',
        'avatar'                         => $request->file('avatar'),
        'allow_members_to_send_messages' => false,
    ],
]);

// Generate or regenerate invite link
$result = $chat->regenerateInvite($user, [
    'expires_at' => now()->addDays(7),
    'max_uses'   => 50,
], $groupId);
// $result['invite_link'] = 'https://your-app.com/api/v1/accept-invite/abc123'

// Accept invite
$chat->acceptInvite($user, $token);
```

---

### User Blocking and Restricting

```php
// Block / unblock (toggles)
$isBlocked = $chat->toggleBlock($user, $targetUserId);

// Restrict / unrestrict (toggles)
$isRestricted = $chat->toggleRestrict($user, $targetUserId);
```

---

## Customizing Existing Behavior

### Override a controller

Publish the stubs:

```bash
php artisan talkbridge:publish --tag=stubs
```

Then create your own controller that extends the package controller:

```php
namespace App\Http\Controllers\Chat;

use RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat\MessageController as BaseController;
use RahatulRabbi\TalkBridge\Http\Requests\Chat\SendMessageRequest;
use Illuminate\Support\Facades\Auth;

class MessageController extends BaseController
{
    public function store(SendMessageRequest $request)
    {
        // Your custom pre-send logic
        $data              = $request->validated();
        $data['message']   = strip_tags($data['message'] ?? '');

        $message = $this->chatService->sendMessage(Auth::user(), $data);

        // Your custom post-send logic (e.g. custom notification)
        // MyNotificationService::notify($message);

        return $this->success($message, 'Message sent.', 201);
    }
}
```

Disable the built-in routes and register yours:

```php
// config/talkbridge.php
'routing' => ['enabled' => false],
```

```php
// routes/api.php
Route::prefix('api/v1')->middleware(['api', 'auth:sanctum', 'talkbridge.last-seen'])->group(function () {
    Route::apiResource('messages', \App\Http\Controllers\Chat\MessageController::class)
        ->only(['store', 'show', 'update']);
    // ... rest of routes from stubs/talkbridge/
});
```

---

### Extend ChatService

```php
namespace App\Services;

use RahatulRabbi\TalkBridge\Services\ChatService;

class AppChatService extends ChatService
{
    public function sendMessage($user, array $data)
    {
        // Custom validation
        if (strlen($data['message'] ?? '') > 5000) {
            throw new \InvalidArgumentException('Message too long.');
        }

        $message = parent::sendMessage($user, $data);

        // Log to your own analytics
        // Analytics::track('message_sent', ['user_id' => $user->id]);

        return $message;
    }
}
```

Bind it in your `AppServiceProvider`:

```php
$this->app->bind(
    \RahatulRabbi\TalkBridge\Services\ChatService::class,
    \App\Services\AppChatService::class
);
```

---

### Listen to real-time events in your backend

```php
// In your EventServiceProvider or AppServiceProvider

use RahatulRabbi\TalkBridge\Events\MessageEvent;
use RahatulRabbi\TalkBridge\Events\ConversationEvent;

Event::listen(MessageEvent::class, function (MessageEvent $event) {
    if ($event->type === 'sent') {
        // e.g. send email digest, update analytics
    }
});

Event::listen(ConversationEvent::class, function (ConversationEvent $event) {
    if ($event->action === 'member_added') {
        // e.g. send welcome message
    }
});
```

---

### Add custom fields to conversation or message responses

Publish the resource stubs and override:

```bash
php artisan talkbridge:publish --tag=stubs
```

Extend the resource:

```php
namespace App\Http\Resources\Chat;

use RahatulRabbi\TalkBridge\Http\Resources\Chat\MessageResource as BaseResource;

class MessageResource extends BaseResource
{
    public function toArray($request): array
    {
        $base = parent::toArray($request);

        // Add your custom fields
        $base['is_bookmarked'] = $request->user()?->bookmarks()->where('message_id', $this->id)->exists();
        $base['custom_meta']   = $this->custom_field ?? null;

        return $base;
    }
}
```

Bind in `AppServiceProvider`:

```php
$this->app->bind(
    \RahatulRabbi\TalkBridge\Http\Resources\Chat\MessageResource::class,
    \App\Http\Resources\Chat\MessageResource::class
);
```

---

### Use helpers directly

```php
// Upload a file to the configured disk
$path = talkbridge_upload_file($file, 'uploads/custom');

// Delete a file
talkbridge_delete_file($path);

// Delete multiple files
talkbridge_delete_files([$path1, $path2]);

// Detect file type from extension
$type = talkbridge_file_type('photo.jpg'); // 'image'

// Get user display name (respects composite name config)
$name = talkbridge_user_name($user);

// Get user avatar
$avatar = talkbridge_user_avatar($user);
```

---

### Use trait methods on your User model

After install, your User model has these methods available:

```php
// Check if user is online
$user->isOnline(); // bool

// Get display name (handles composite columns)
$user->getChatDisplayName(); // string

// Get avatar URL
$user->getChatAvatar(); // string|null

// Get last seen as human diff
$user->getChatLastSeen(); // '2 minutes ago'

// Blocking
$user->hasBlocked($otherUser);     // bool
$user->isBlockedBy($otherUser);    // bool
$user->blockedUsers();             // BelongsToMany
$user->blockedByUsers();           // BelongsToMany

// Restricting
$user->hasRestricted($otherUser);  // bool
$user->restrictedUsers();          // BelongsToMany
$user->restrictedByUsers();        // BelongsToMany

// Device tokens
$user->deviceTokens();             // HasMany
```

---

## Frontend Integration

### Vue 3 / React — echo.js (Reverb)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;
window.axios  = axios;
axios.defaults.withCredentials = true;

window.Echo = new Echo({
    broadcaster:       'reverb',
    key:               import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:            import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort:            Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort:           Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS:          (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

### Channel subscriptions

```javascript
// Global online presence
window.Echo.join('online')
    .here(users   => { onlineUsers.value = users; })
    .joining(user => { onlineUsers.value.push(user); })
    .leaving(user => { onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id); });

// Personal notifications
window.Echo.private(`user.${authUser.id}`)
    .listen('.ConversationEvent', event => {
        if (event.action === 'added')   { addToConversationList(event.conversation); }
        if (event.action === 'removed') { removeFromConversationList(event.conversation.id); }
        if (event.action === 'blocked') { markAsBlocked(event.conversation.id); }
    });

// Conversation channel
const channel = window.Echo.join(`conversation.${conversationId}`)
    .listen('.MessageEvent', event => {
        if (event.type === 'sent')               { addMessage(event.payload); }
        if (event.type === 'updated')            { updateMessage(event.payload); }
        if (event.type === 'deleted_for_everyone'){ markAsUnsent(event.payload); }
        if (event.type === 'reaction')           { updateReactions(event.payload); }
        if (event.type === 'seen')               { updateReadStatus(event.payload); }
        if (event.type === 'delivered')          { updateDeliveryStatus(event.payload); }
        if (event.type === 'pinned')             { markPinned(event.payload); }
    })
    .listen('.ConversationEvent', event => {
        if (event.action === 'member_added') { refreshMemberList(); }
        if (event.action === 'updated')      { refreshGroupInfo(); }
    })
    .listenForWhisper('typing', ({ name, isTyping }) => {
        typingUser.value = isTyping ? name : null;
    });

// Typing indicator
channel.whisper('typing', { userId: authUser.id, name: authUser.name, isTyping: true });
```

For Flutter and React Native integration, see `docs/mobile/README.md`.

---

## API Endpoints

All under `/api/v1` with Sanctum auth (`Authorization: Bearer {token}`).

### Conversations
| Method | Endpoint | Description |
|---|---|---|
| GET | `/conversations` | List all (paginated, `?q=search`) |
| POST | `/conversations` | Create group |
| POST | `/conversations/private` | Start or get private conversation |
| DELETE | `/conversations/{id}` | Remove for current user only |
| GET | `/conversations/{id}/media` | Media library |

### Messages
| Method | Endpoint | Body / Notes |
|---|---|---|
| POST | `/messages` | `{conversation_id, message, message_type}` |
| GET | `/messages/{conversation}` | Paginated, `?q=search` |
| PUT | `/messages/{message}` | `{message}` |
| DELETE | `/messages/delete-for-me` | `{message_ids: [1,2]}` |
| DELETE | `/messages/delete-for-everyone` | `{message_ids: [1]}` |
| GET | `/messages/seen/{conversation}` | Mark all seen (on open) |
| POST | `/messages/mark-seen` | `{conversation_id, message_ids:[...]}` |
| GET | `/messages/delivered/{conversation}` | Mark as delivered |
| POST | `/messages/{message}/forward` | `{conversation_ids: [2, 3]}` |
| POST | `/messages/{message}/toggle-pin` | Pin or unpin |
| GET | `/messages/{conversation}/pinned-messages` | All pinned |

### Reactions
| Method | Endpoint | Body |
|---|---|---|
| POST | `/messages/{message}/reaction` | `{"reaction":"❤️"}` |
| GET | `/messages/{message}/reaction` | Returns grouped reactions |

### Group
| Method | Endpoint | Body / Notes |
|---|---|---|
| POST | `/group/{id}/update` | `{name, group:{description, avatar, type, ...settings}}` |
| POST | `/group/{id}/members/add` | `{member_ids: [5,6]}` |
| POST | `/group/{id}/members/remove` | `{member_ids: [5]}` |
| GET | `/group/{id}/members` | All members |
| POST | `/group/{id}/admins/add` | `{member_ids: [5]}` |
| POST | `/group/{id}/admins/remove` | `{member_ids: [5]}` |
| POST | `/group/{id}/mute` | `{minutes: 60}` / `-1`=forever / `0`=unmute |
| POST | `/group/{id}/leave` | Leave the group |
| DELETE | `/group/{id}/delete-group` | Super admin only |
| POST | `/group/{id}/regenerate-invite` | `{expires_at?, max_uses?}` |
| GET | `/accept-invite/{token}` | Join via invite link |

### Users
| Method | Endpoint | Notes |
|---|---|---|
| GET | `/available-users?search=name` | Search users |
| GET | `/online-users` | Currently online |
| POST | `/users/{user}/block-toggle` | Block or unblock |
| POST | `/users/{user}/restrict-toggle` | Restrict or unrestrict |


### API Docx (Postman Collection)
* [API Documentation](https://documenter.getpostman.com/view/39751280/2sBXVcjroe)


---

## Real-Time Events

### ConversationEvent — `user.{id}` (private) or `conversation.{id}` (presence)

| Action | When |
|---|---|
| `added` | User added to / created a conversation |
| `removed` | Removed from group |
| `left` | Left group |
| `updated` | Group name / avatar / settings changed |
| `deleted` | Group deleted |
| `blocked` / `unblocked` | Block status changed |
| `unmuted` | Auto-unmuted by scheduler |
| `member_added` / `member_left` | Group membership changed |
| `admin_added` / `admin_removed` | Role changed |

### MessageEvent — `conversation.{id}` (presence)

| Type | When |
|---|---|
| `sent` | New message |
| `updated` | Message edited |
| `deleted_for_everyone` | Message unsent |
| `deleted_permanent` | Hard deleted |
| `reaction` | Reaction toggled |
| `delivered` / `seen` | Status update |
| `pinned` / `unpinned` | Pin toggled |

---

## Artisan Commands

| Command | Description |
|---|---|
| `composer require rahatulrabbi/talkbridge` | Latest stable |
| `composer require rahatulrabbi/talkbridge:1.0.0` | Specific version |
| `composer require rahatulrabbi/talkbridge:"^1.0"` | Version range |
| `composer require rahatulrabbi/talkbridge:">=1.0.0"` | Minimum version |
| `php artisan talkbridge:install` | Install wizard |
| `php artisan talkbridge:install --broadcaster=pusher --push=fcm` | Non-interactive install |
| `php artisan talkbridge:install --force` | Overwrite existing published files |
| `php artisan talkbridge:install --no-migrate` | Skip running migrations |
| `php artisan talkbridge:uninstall` | Remove everything |
| `php artisan talkbridge:uninstall --keep-data` | Remove code, keep database tables |
| `php artisan talkbridge:uninstall --keep-packages` | Remove code, keep Composer packages |
| `php artisan talkbridge:uninstall --force` | Skip all confirmation prompts |
| `php artisan talkbridge:publish` | Interactive asset publisher |
| `php artisan talkbridge:publish --tag=config` | Re-publish config only |
| `php artisan talkbridge:publish --tag=migrations` | Re-publish migrations only |
| `php artisan talkbridge:publish --tag=stubs` | Publish stubs for customization |
| `php artisan talkbridge:publish --tag=lang` | Publish language files |
| `php artisan talkbridge:publish --tag=all` | Publish all assets |
| `php artisan talkbridge:publish --force` | Overwrite existing published files |
| `php artisan talkbridge:version` | Show installed version |
| `php artisan talkbridge:version --check` | Check Packagist for a newer version |
| `php artisan talkbridge:update` | Update to latest version |
| `php artisan talkbridge:update --to=1.0.0` | Install a specific version |
| `php artisan talkbridge:update --force` | Update and overwrite published config and stubs |
| `php artisan talkbridge:update --skip-composer` | Skip composer update (useful in CI) |
| `php artisan talkbridge:auto-unmute` | Process expired mutes (auto-run by scheduler every minute) |

---

## Uninstall

```bash
php artisan talkbridge:uninstall
```

TalkBridge reads `TALKBRIDGE_INSTALLED_BROADCASTER` and `TALKBRIDGE_INSTALLED_PUSH`
from `.env` to know exactly which packages it installed, so it only removes those —
it never touches packages that were already in your project.

After uninstall:

```bash
composer remove rahatulrabbi/talkbridge
```

---

### API Docx (Postman Collection)
* [API Documentation](https://documenter.getpostman.com/view/39751280/2sBXVcjroe)

### Resources:
* [Packagist](https://packagist.org/packages/rahatulrabbi/talkbridge)
* [Video Guide](https://www.youtube.com/watch?v=fb0TB-QVal0)
* [Technical Documentation](https://github.com/learnwithfair/talkbridge/tree/main/docs/mobile)
* [Implement Documentation](https://rahatulrabbi.vercel.app/blog/talkbridge-automated-real-time-chat-system-for-laravel-11-13)


## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## License

MIT — see [LICENSE](LICENSE).

**Author:** MD. RAHATUL RABBI — [github.com/learnwithfair](https://github.com/learnwithfair)
