# Mobile Integration Guide

This guide covers integration with Flutter and React Native.
Both integrations use the same Laravel Chat REST API and WebSocket channels.

---

## Flutter

### File: `docs/mobile/flutter_integration.dart`

### Dependencies

Add to `pubspec.yaml`:

```yaml
dependencies:
  pusher_channels_flutter: ^2.0.0
  http: ^1.2.0
  shared_preferences: ^2.2.0
  firebase_messaging: ^14.0.0
  firebase_core: ^2.0.0
```

Run:

```bash
flutter pub get
```

### Setup

**1. Store the auth token after login:**

```dart
final prefs = await SharedPreferences.getInstance();
await prefs.setString('auth_token', 'your-sanctum-token');
```

**2. Initialize the socket:**

```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();

  final prefs = await SharedPreferences.getInstance();
  final token = prefs.getString('auth_token') ?? '';

  await ChatSocket().init(authToken: token);
  await PushNotificationService().init();

  runApp(const MyApp());
}
```

**3. Subscribe to channels:**

```dart
// Global online presence
await ChatSocket().subscribeOnline(
  onHere:    (users) => print('Online: $users'),
  onJoining: (user)  => print('Joined: $user'),
  onLeaving: (user)  => print('Left: $user'),
);

// Personal channel
await ChatSocket().subscribeUser(
  userId: authUserId,
  onConversationEvent: (action, conversation) {
    if (action == 'added') {
      // Add to conversation list
    }
  },
);

// Conversation channel
await ChatSocket().subscribeConversation(
  conversationId: conversationId,
  onMessageEvent: (data) {
    final type    = data['type'];
    final payload = data['payload'];
    if (type == 'sent') {
      setState(() => messages.insert(0, ChatMessage.fromJson(payload)));
    }
  },
  onConversationEvent: (action, _) {},
  onTyping: (data) {
    setState(() => typingUser = data['name']);
    Future.delayed(const Duration(seconds: 3), () => setState(() => typingUser = null));
  },
);
```

**4. Send a message:**

```dart
final ChatApi api = ChatApi();

// Text
await api.sendTextMessage(conversationId: 5, message: 'Hello');

// File
await api.sendFileMessage(
  conversationId: 5,
  file:           File('/path/to/image.jpg'),
  messageType:    'image',
);
```

**5. Typing indicator:**

```dart
// Sender
ChatSocket().sendTyping(conversationId, authUserId, authUserName);

// Receiver (handled in subscribeConversation onTyping callback above)
```

**6. Clean up:**

```dart
@override
void dispose() {
  ChatSocket().leaveConversation(conversationId);
  super.dispose();
}
```

---

## React Native

### File: `docs/mobile/react_native_integration.ts`

### Dependencies

```bash
npm install \
  @pusher/pusher-websocket-react-native \
  axios \
  @react-native-async-storage/async-storage \
  @react-native-firebase/app \
  @react-native-firebase/messaging

# iOS only
npx pod-install
```

### Setup

**1. Store auth token after login:**

```typescript
import AsyncStorage from '@react-native-async-storage/async-storage';
await AsyncStorage.setItem('auth_token', 'your-sanctum-token');
```

**2. Initialize in App.tsx:**

```typescript
import { chatSocket }          from './src/services/chatSocket';
import { initPushNotifications } from './src/services/pushNotificationService';

export default function App() {
  useEffect(() => {
    chatSocket.init();
    initPushNotifications();
  }, []);

  return <NavigationContainer>...</NavigationContainer>;
}
```

**3. Use the hook in a screen:**

```typescript
import { useChat } from '../hooks/useChat';

function ChatScreen({ conversationId, userId, userName }) {
  const {
    messages,
    loading,
    typingUser,
    sendMessage,
    sendTyping,
    deleteMessage,
    reactToMessage,
  } = useChat(conversationId, userId, userName);

  return (
    <View style={{ flex: 1 }}>
      <FlatList
        data={messages}
        inverted
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => <MessageBubble message={item} />}
      />
      {typingUser && (
        <Text style={{ padding: 8 }}>{typingUser} is typing...</Text>
      )}
      <TextInput
        onChangeText={() => sendTyping()}
        onSubmitEditing={(e) => sendMessage(e.nativeEvent.text)}
        placeholder="Type a message"
      />
    </View>
  );
}
```

**4. Send a file:**

```typescript
import { launchImageLibrary } from 'react-native-image-picker';

const result = await launchImageLibrary({ mediaType: 'photo' });
const asset  = result.assets?.[0];

if (asset) {
  await chatApi.sendFileMessage({
    conversationId: conversationId,
    fileUri:        asset.uri!,
    fileName:       asset.fileName!,
    mimeType:       asset.type!,
    messageType:    'image',
  });
}
```

---

## FCM Push Notification Payload

The backend sends the following payload with every push notification:

```json
{
  "type":            "chat_message",
  "conversation_id": "15",
  "message_id":      "456",
  "sender_id":       "3"
}
```

Use `conversation_id` to navigate the user to the correct conversation when they tap the notification.

---

## Real-Time Event Reference

### MessageEvent types

| type | When fired |
|------|------------|
| `sent` | New message sent |
| `updated` | Message edited |
| `deleted_for_everyone` | Message unsent |
| `deleted_permanent` | Hard deleted |
| `reaction` | Reaction toggled |
| `delivered` | Delivered to recipient |
| `seen` | Seen by recipient |
| `pinned` | Message pinned |
| `unpinned` | Message unpinned |

### ConversationEvent actions

| action | When fired |
|--------|------------|
| `added` | New conversation or user re-added |
| `removed` | User removed from group |
| `left` | User left group |
| `updated` | Group info changed |
| `deleted` | Group deleted |
| `blocked` | User blocked |
| `unblocked` | User unblocked |
| `unmuted` | Conversation unmuted |
| `member_added` | Member joined group |
| `member_left` | Member left group |
| `admin_added` | Member promoted to admin |
| `admin_removed` | Admin demoted |

---

## Channel Names

| Channel | Type | Purpose |
|---------|------|---------|
| `presence-online` | Presence | Global online users |
| `private-user.{id}` | Private | Personal notifications |
| `presence-conversation.{id}` | Presence | Messages, typing, presence |

> **Note:** The Pusher SDK prefixes presence channels with `presence-` and private channels with `private-`. The server-side channel name (in `routes/channels.php`) does not include the prefix — it is `online`, `user.{id}`, `conversation.{id}`.
