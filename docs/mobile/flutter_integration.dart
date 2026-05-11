// ============================================================
// Laravel Chat — Flutter Integration
// File: lib/services/chat_service.dart
// ============================================================
//
// Dependencies — add to pubspec.yaml:
//
// dependencies:
//   pusher_channels_flutter: ^2.0.0
//   http: ^1.2.0
//   shared_preferences: ^2.2.0
//   firebase_messaging: ^14.0.0   # for FCM push notifications
//
// Run:
//   flutter pub get
// ============================================================

// ============================================================
// lib/config/chat_config.dart
// ============================================================

class ChatConfig {
  static const String baseUrl        = 'https://your-app.com/api/v1';
  static const String reverbHost     = 'your-app.com';
  static const int    reverbPort     = 443;
  static const String reverbScheme   = 'https';
  static const String reverbAppKey   = 'your-reverb-app-key';
  static const bool   forceTLS       = true;
}


// ============================================================
// lib/models/chat_message.dart
// ============================================================

class ChatMessage {
  final int     id;
  final int     conversationId;
  final Sender  sender;
  final String? message;
  final String  messageType;
  final bool    isDeletedForEveryone;
  final bool    isPinned;
  final bool    isMine;
  final Map<String, int> reactions;
  final List<MessageAttachment> attachments;
  final String  createdAt;

  ChatMessage({
    required this.id,
    required this.conversationId,
    required this.sender,
    this.message,
    required this.messageType,
    required this.isDeletedForEveryone,
    required this.isPinned,
    required this.isMine,
    required this.reactions,
    required this.attachments,
    required this.createdAt,
  });

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    final reactionsRaw = json['reactions']?['reactions'] as Map<String, dynamic>? ?? {};
    return ChatMessage(
      id:                   json['id'],
      conversationId:       json['conversation_id'],
      sender:               Sender.fromJson(json['sender']),
      message:              json['message'],
      messageType:          json['message_type'] ?? 'text',
      isDeletedForEveryone: json['is_deleted_for_everyone'] ?? false,
      isPinned:             json['is_pinned'] ?? false,
      isMine:               json['is_mine'] ?? false,
      reactions:            reactionsRaw.map((k, v) => MapEntry(k, v as int)),
      attachments:          (json['attachments'] as List? ?? [])
                                .map((a) => MessageAttachment.fromJson(a))
                                .toList(),
      createdAt:            json['created_at'] ?? '',
    );
  }
}

class Sender {
  final int    id;
  final String name;

  Sender({required this.id, required this.name});

  factory Sender.fromJson(Map<String, dynamic> json) =>
      Sender(id: json['id'], name: json['name']);
}

class MessageAttachment {
  final int    id;
  final String type;
  final String path;
  final String? name;
  final int?   size;

  MessageAttachment({
    required this.id,
    required this.type,
    required this.path,
    this.name,
    this.size,
  });

  factory MessageAttachment.fromJson(Map<String, dynamic> json) =>
      MessageAttachment(
        id:   json['id'],
        type: json['type'],
        path: json['path'],
        name: json['name'],
        size: json['size'],
      );
}


// ============================================================
// lib/models/conversation.dart
// ============================================================

class Conversation {
  final int     id;
  final String  type;
  final String? name;
  final bool    isMuted;
  final bool    isBlocked;
  final bool    isOnline;
  final int     unreadCount;
  final String? role;
  final LastMessage? lastMessage;
  final ConversationReceiver? receiver;

  Conversation({
    required this.id,
    required this.type,
    this.name,
    required this.isMuted,
    required this.isBlocked,
    required this.isOnline,
    required this.unreadCount,
    this.role,
    this.lastMessage,
    this.receiver,
  });

  factory Conversation.fromJson(Map<String, dynamic> json) => Conversation(
        id:          json['id'],
        type:        json['type'],
        name:        json['name'],
        isMuted:     json['is_muted'] ?? false,
        isBlocked:   json['is_blocked'] ?? false,
        isOnline:    json['is_online'] ?? false,
        unreadCount: json['unread_count'] ?? 0,
        role:        json['role'],
        lastMessage: json['last_message'] != null
            ? LastMessage.fromJson(json['last_message'])
            : null,
        receiver: json['receiver'] != null
            ? ConversationReceiver.fromJson(json['receiver'])
            : null,
      );

  String get displayName {
    if (type == 'private' && receiver != null) return receiver!.name;
    return name ?? 'Unknown';
  }

  String? get displayAvatar {
    if (type == 'private' && receiver != null) return receiver!.avatarPath;
    return null;
  }
}

class LastMessage {
  final int     id;
  final String? message;
  final String  createdAt;

  LastMessage({required this.id, this.message, required this.createdAt});

  factory LastMessage.fromJson(Map<String, dynamic> json) => LastMessage(
        id:        json['id'],
        message:   json['message'],
        createdAt: json['created_at'] ?? '',
      );
}

class ConversationReceiver {
  final int    id;
  final String name;
  final String? avatarPath;
  final bool   isOnline;

  ConversationReceiver({
    required this.id,
    required this.name,
    this.avatarPath,
    required this.isOnline,
  });

  factory ConversationReceiver.fromJson(Map<String, dynamic> json) =>
      ConversationReceiver(
        id:         json['id'],
        name:       json['name'],
        avatarPath: json['avatar_path'],
        isOnline:   json['is_online'] ?? false,
      );
}


// ============================================================
// lib/services/chat_api.dart
// ============================================================

import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ChatApi {
  final String _base = ChatConfig.baseUrl;

  // ------------------------------------------------------------------
  // Auth token
  // ------------------------------------------------------------------

  Future<String?> _token() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, String>> _headers({bool multipart = false}) async {
    final token = await _token();
    return {
      'Authorization': 'Bearer $token',
      'Accept':        'application/json',
      if (!multipart) 'Content-Type': 'application/json',
    };
  }

  // ------------------------------------------------------------------
  // Conversations
  // ------------------------------------------------------------------

  Future<List<Conversation>> getConversations({int page = 1, String? search}) async {
    final uri = Uri.parse('$_base/conversations').replace(
      queryParameters: {
        'page': page.toString(),
        if (search != null && search.isNotEmpty) 'q': search,
      },
    );

    final res = await http.get(uri, headers: await _headers());
    _assertOk(res);

    final List data = json.decode(res.body)['data'] as List;
    return data.map((e) => Conversation.fromJson(e)).toList();
  }

  Future<Conversation> startPrivateConversation(int receiverId) async {
    final res = await http.post(
      Uri.parse('$_base/conversations/private'),
      headers: await _headers(),
      body: json.encode({'receiver_id': receiverId}),
    );
    _assertOk(res);
    return Conversation.fromJson(json.decode(res.body)['data']);
  }

  Future<Conversation> createGroup({
    required String name,
    required List<int> participantIds,
    String? description,
    String groupType = 'private',
  }) async {
    final res = await http.post(
      Uri.parse('$_base/conversations'),
      headers: await _headers(),
      body: json.encode({
        'name':         name,
        'participants': participantIds,
        'group': {
          'description': description,
          'type':        groupType,
        },
      }),
    );
    _assertOk(res);
    return Conversation.fromJson(json.decode(res.body)['data']);
  }

  Future<void> deleteConversation(int conversationId) async {
    final res = await http.delete(
      Uri.parse('$_base/conversations/$conversationId'),
      headers: await _headers(),
    );
    _assertOk(res);
  }

  // ------------------------------------------------------------------
  // Messages
  // ------------------------------------------------------------------

  Future<List<ChatMessage>> getMessages(
    int conversationId, {
    int page    = 1,
    int perPage = 20,
    String? search,
  }) async {
    final uri = Uri.parse('$_base/messages/$conversationId').replace(
      queryParameters: {
        'page':     page.toString(),
        'per_page': perPage.toString(),
        if (search != null && search.isNotEmpty) 'q': search,
      },
    );

    final res = await http.get(uri, headers: await _headers());
    _assertOk(res);

    final List data = json.decode(res.body)['data'] as List;
    return data.map((e) => ChatMessage.fromJson(e)).toList();
  }

  Future<ChatMessage> sendTextMessage({
    required int    conversationId,
    required String message,
    int? replyToMessageId,
    String messageType = 'text',
  }) async {
    final res = await http.post(
      Uri.parse('$_base/messages'),
      headers: await _headers(),
      body: json.encode({
        'conversation_id':      conversationId,
        'message':              message,
        'message_type':         messageType,
        if (replyToMessageId != null)
          'reply_to_message_id': replyToMessageId,
      }),
    );
    _assertOk(res);
    return ChatMessage.fromJson(json.decode(res.body)['data']);
  }

  Future<ChatMessage> sendFileMessage({
    required int    conversationId,
    required File   file,
    required String messageType, // 'image' | 'video' | 'audio' | 'file'
    int? replyToMessageId,
  }) async {
    final headers = await _headers(multipart: true);
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('$_base/messages'),
    )
      ..headers.addAll(headers)
      ..fields['conversation_id'] = conversationId.toString()
      ..fields['message_type']    = messageType
      ..files.add(await http.MultipartFile.fromPath('attachments[0][path]', file.path));

    if (replyToMessageId != null) {
      request.fields['reply_to_message_id'] = replyToMessageId.toString();
    }

    final streamed = await request.send();
    final res      = await http.Response.fromStream(streamed);
    _assertOk(res);
    return ChatMessage.fromJson(json.decode(res.body)['data']);
  }

  Future<void> deleteForMe(List<int> messageIds) async {
    final res = await http.delete(
      Uri.parse('$_base/messages/delete-for-me'),
      headers: await _headers(),
      body: json.encode({'message_ids': messageIds}),
    );
    _assertOk(res);
  }

  Future<void> deleteForEveryone(List<int> messageIds) async {
    final res = await http.delete(
      Uri.parse('$_base/messages/delete-for-everyone'),
      headers: await _headers(),
      body: json.encode({'message_ids': messageIds}),
    );
    _assertOk(res);
  }

  Future<void> forwardMessage(int messageId, List<int> conversationIds) async {
    final res = await http.post(
      Uri.parse('$_base/messages/$messageId/forward'),
      headers: await _headers(),
      body: json.encode({'conversation_ids': conversationIds}),
    );
    _assertOk(res);
  }

  Future<void> pinToggle(int messageId) async {
    final res = await http.post(
      Uri.parse('$_base/messages/$messageId/toggle-pin'),
      headers: await _headers(),
    );
    _assertOk(res);
  }

  Future<void> markAsSeen(int conversationId) async {
    await http.get(
      Uri.parse('$_base/messages/seen/$conversationId'),
      headers: await _headers(),
    );
  }

  Future<void> markSpecificSeen({
    required int       conversationId,
    required List<int> messageIds,
  }) async {
    await http.post(
      Uri.parse('$_base/messages/mark-seen'),
      headers: await _headers(),
      body: json.encode({
        'conversation_id': conversationId,
        'message_ids':     messageIds,
      }),
    );
  }

  Future<void> markDelivered(int conversationId) async {
    await http.get(
      Uri.parse('$_base/messages/delivered/$conversationId'),
      headers: await _headers(),
    );
  }

  // ------------------------------------------------------------------
  // Reactions
  // ------------------------------------------------------------------

  Future<void> toggleReaction(int messageId, String reaction) async {
    final res = await http.post(
      Uri.parse('$_base/messages/$messageId/reaction'),
      headers: await _headers(),
      body: json.encode({'reaction': reaction}),
    );
    _assertOk(res);
  }

  // ------------------------------------------------------------------
  // Group management
  // ------------------------------------------------------------------

  Future<void> addMembers(int conversationId, List<int> memberIds) async {
    final res = await http.post(
      Uri.parse('$_base/group/$conversationId/members/add'),
      headers: await _headers(),
      body: json.encode({'member_ids': memberIds}),
    );
    _assertOk(res);
  }

  Future<void> removeMembers(int conversationId, List<int> memberIds) async {
    final res = await http.post(
      Uri.parse('$_base/group/$conversationId/members/remove'),
      headers: await _headers(),
      body: json.encode({'member_ids': memberIds}),
    );
    _assertOk(res);
  }

  Future<void> muteGroup(int conversationId, {int minutes = -1}) async {
    // minutes: -1 = forever, 0 = unmute, positive = duration
    await http.post(
      Uri.parse('$_base/group/$conversationId/mute'),
      headers: await _headers(),
      body: json.encode({'minutes': minutes}),
    );
  }

  Future<void> leaveGroup(int conversationId) async {
    await http.post(
      Uri.parse('$_base/group/$conversationId/leave'),
      headers: await _headers(),
    );
  }

  Future<Map<String, dynamic>> regenerateInvite(int conversationId) async {
    final res = await http.post(
      Uri.parse('$_base/group/$conversationId/regenerate-invite'),
      headers: await _headers(),
    );
    _assertOk(res);
    return json.decode(res.body)['data'] as Map<String, dynamic>;
  }

  // ------------------------------------------------------------------
  // Users
  // ------------------------------------------------------------------

  Future<void> blockToggle(int userId) async {
    await http.post(
      Uri.parse('$_base/users/$userId/block-toggle'),
      headers: await _headers(),
    );
  }

  Future<void> restrictToggle(int userId) async {
    await http.post(
      Uri.parse('$_base/users/$userId/restrict-toggle'),
      headers: await _headers(),
    );
  }

  // ------------------------------------------------------------------
  // Device token (FCM push notifications)
  // ------------------------------------------------------------------

  Future<void> registerDeviceToken(String token, String platform) async {
    await http.post(
      Uri.parse('$_base/device-tokens'),
      headers: await _headers(),
      body: json.encode({'token': token, 'platform': platform}),
    );
  }

  // ------------------------------------------------------------------
  // Internal
  // ------------------------------------------------------------------

  void _assertOk(http.Response res) {
    if (res.statusCode < 200 || res.statusCode >= 300) {
      final body = json.decode(res.body);
      throw Exception(body['message'] ?? 'HTTP ${res.statusCode}');
    }
  }
}


// ============================================================
// lib/services/chat_socket.dart
// ============================================================

import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

typedef MessageHandler      = void Function(Map<String, dynamic> payload);
typedef ConversationHandler = void Function(String action, Map<String, dynamic> payload);

class ChatSocket {
  static final ChatSocket _instance = ChatSocket._internal();
  factory ChatSocket() => _instance;
  ChatSocket._internal();

  late PusherChannelsFlutter _pusher;
  bool _initialized = false;

  // Channel references
  final Map<String, PusherChannel> _channels = {};

  // ------------------------------------------------------------------
  // Init
  // ------------------------------------------------------------------

  Future<void> init({required String authToken}) async {
    if (_initialized) return;

    _pusher = PusherChannelsFlutter.getInstance();

    await _pusher.init(
      apiKey:     ChatConfig.reverbAppKey,
      cluster:    'mt1',                          // required by SDK but overridden below
      wsHost:     ChatConfig.reverbHost,
      wsPort:     ChatConfig.reverbPort,
      wssPort:    ChatConfig.reverbPort,
      useTLS:     ChatConfig.forceTLS,
      authEndpoint: '${ChatConfig.baseUrl.replaceAll('/api/v1', '')}/broadcasting/auth',
      authParams: PusherAuthParams(
        headers: {
          'Authorization': 'Bearer $authToken',
          'Accept':        'application/json',
        },
      ),
    );

    await _pusher.connect();
    _initialized = true;
  }

  Future<void> disconnect() async {
    await _pusher.disconnect();
    _initialized = false;
    _channels.clear();
  }

  // ------------------------------------------------------------------
  // Global online presence channel
  // ------------------------------------------------------------------

  Future<void> subscribeOnline({
    required void Function(List<dynamic> users)  onHere,
    required void Function(Map<String, dynamic>) onJoining,
    required void Function(Map<String, dynamic>) onLeaving,
  }) async {
    final channel = await _pusher.subscribe(
      channelName: 'presence-online',
      onMemberAdded:   (member) => onJoining({'id': member.userId, ...?member.userInfo}),
      onMemberRemoved: (member) => onLeaving({'id': member.userId}),
      onSubscriptionSucceeded: (channelName, data) {
        final members = (data['presence']?['hash'] as Map?)?.values.toList() ?? [];
        onHere(members);
      },
    );
    _channels['online'] = channel;
  }

  // ------------------------------------------------------------------
  // Personal private channel  (user.{id})
  // ------------------------------------------------------------------

  Future<void> subscribeUser({
    required int userId,
    required ConversationHandler onConversationEvent,
  }) async {
    final channel = await _pusher.subscribe(
      channelName: 'private-user.$userId',
      onEvent: (event) {
        if (event.eventName == 'ConversationEvent') {
          final data = _decode(event.data);
          onConversationEvent(
            data['action'] as String,
            data['conversation'] as Map<String, dynamic>? ?? {},
          );
        }
      },
    );
    _channels['user.$userId'] = channel;
  }

  // ------------------------------------------------------------------
  // Conversation presence channel  (conversation.{id})
  // ------------------------------------------------------------------

  Future<void> subscribeConversation({
    required int conversationId,
    required MessageHandler      onMessageEvent,
    required ConversationHandler onConversationEvent,
    void Function(Map<String, dynamic>)? onTyping,
    void Function(List<dynamic>)?        onHere,
    void Function(Map<String, dynamic>)? onJoining,
    void Function(Map<String, dynamic>)? onLeaving,
  }) async {
    final channel = await _pusher.subscribe(
      channelName: 'presence-conversation.$conversationId',

      onMemberAdded:   (m) => onJoining?.call({'id': m.userId, ...?m.userInfo}),
      onMemberRemoved: (m) => onLeaving?.call({'id': m.userId}),

      onSubscriptionSucceeded: (_, data) {
        final members = (data['presence']?['hash'] as Map?)?.values.toList() ?? [];
        onHere?.call(members);
      },

      onEvent: (event) {
        if (event.eventName == 'MessageEvent') {
          final data = _decode(event.data);
          onMessageEvent(data);
        }

        if (event.eventName == 'ConversationEvent') {
          final data = _decode(event.data);
          onConversationEvent(
            data['action'] as String,
            data['conversation'] as Map<String, dynamic>? ?? {},
          );
        }

        if (event.eventName == 'client-typing') {
          onTyping?.call(_decode(event.data));
        }
      },
    );

    _channels['conversation.$conversationId'] = channel;
  }

  // ------------------------------------------------------------------
  // Typing indicator whisper
  // ------------------------------------------------------------------

  void sendTyping(int conversationId, int userId, String userName) {
    final channel = _channels['conversation.$conversationId'];
    channel?.trigger(
      eventName: 'client-typing',
      data: json.encode({'userId': userId, 'name': userName, 'isTyping': true}),
    );
  }

  // ------------------------------------------------------------------
  // Unsubscribe
  // ------------------------------------------------------------------

  Future<void> leaveConversation(int conversationId) async {
    await _pusher.unsubscribe(channelName: 'presence-conversation.$conversationId');
    _channels.remove('conversation.$conversationId');
  }

  Future<void> leaveUser(int userId) async {
    await _pusher.unsubscribe(channelName: 'private-user.$userId');
    _channels.remove('user.$userId');
  }

  // ------------------------------------------------------------------
  // Internal
  // ------------------------------------------------------------------

  Map<String, dynamic> _decode(dynamic raw) {
    if (raw is String) return json.decode(raw) as Map<String, dynamic>;
    if (raw is Map)    return raw.cast<String, dynamic>();
    return {};
  }
}


// ============================================================
// lib/services/push_notification_service.dart
// ============================================================

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:shared_preferences/shared_preferences.dart';

class PushNotificationService {
  final FirebaseMessaging _fcm = FirebaseMessaging.instance;
  final ChatApi           _api = ChatApi();

  Future<void> init() async {
    // Request permissions (iOS)
    await _fcm.requestPermission(alert: true, badge: true, sound: true);

    // Get token and register with backend
    final token    = await _fcm.getToken();
    final platform = _detectPlatform();

    if (token != null) {
      await _api.registerDeviceToken(token, platform);
      _saveToken(token);
    }

    // Handle token refresh
    _fcm.onTokenRefresh.listen((newToken) async {
      await _api.registerDeviceToken(newToken, platform);
      _saveToken(newToken);
    });

    // Foreground messages
    FirebaseMessaging.onMessage.listen(_handleForeground);

    // Background tap (app in background)
    FirebaseMessaging.onMessageOpenedApp.listen(_handleTap);

    // App terminated — opened via notification
    final initial = await _fcm.getInitialMessage();
    if (initial != null) _handleTap(initial);
  }

  void _handleForeground(RemoteMessage message) {
    // Show in-app notification banner
    final data           = message.data;
    final conversationId = data['conversation_id'];
    final senderId       = data['sender_id'];

    // Emit event to update UI if conversation is open
    // Example: chatEventBus.fire(NewMessagePushEvent(conversationId, senderId));
  }

  void _handleTap(RemoteMessage message) {
    final data           = message.data;
    final conversationId = data['conversation_id'];

    // Navigate to conversation
    // Example: navigatorKey.currentState?.push(ConversationRoute(int.parse(conversationId)));
  }

  String _detectPlatform() {
    // ignore: import_of_legacy_library_into_null_safe
    if (identical(0, 0.0)) return 'web';
    try {
      // Platform detection
      return 'android'; // or 'ios' — use dart:io Platform in real code
    } catch (_) {
      return 'unknown';
    }
  }

  Future<void> _saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('fcm_token', token);
  }
}


// ============================================================
// lib/screens/chat_screen.dart  — Example usage
// ============================================================

/*

class ChatScreen extends StatefulWidget {
  final int conversationId;
  final int authUserId;
  final String authUserName;

  const ChatScreen({
    super.key,
    required this.conversationId,
    required this.authUserId,
    required this.authUserName,
  });

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final ChatApi    _api    = ChatApi();
  final ChatSocket _socket = ChatSocket();

  final List<ChatMessage>    _messages   = [];
  final TextEditingController _controller = TextEditingController();

  String? _typingUser;
  Timer?  _typingTimer;

  @override
  void initState() {
    super.initState();
    _loadMessages();
    _subscribe();
    _api.markAsSeen(widget.conversationId);
    _api.markDelivered(widget.conversationId);
  }

  Future<void> _loadMessages() async {
    final messages = await _api.getMessages(widget.conversationId);
    setState(() => _messages.addAll(messages));
  }

  void _subscribe() {
    _socket.subscribeConversation(
      conversationId: widget.conversationId,

      onMessageEvent: (data) {
        final type    = data['type'] as String;
        final payload = data['payload'] as Map<String, dynamic>;

        setState(() {
          if (type == 'sent') {
            _messages.insert(0, ChatMessage.fromJson(payload));
          } else if (type == 'deleted_for_everyone') {
            final idx = _messages.indexWhere((m) => m.id == payload['id']);
            if (idx != -1) _messages[idx] = ChatMessage.fromJson(payload);
          } else if (type == 'reaction') {
            // Update reactions on relevant message
          } else if (type == 'seen') {
            // Update status indicators
          }
        });
      },

      onConversationEvent: (action, conversation) {
        if (action == 'member_added') {
          // Refresh member list
        }
      },

      onTyping: (data) {
        if (data['isTyping'] == true) {
          setState(() => _typingUser = data['name']);
          _typingTimer?.cancel();
          _typingTimer = Timer(const Duration(seconds: 3), () {
            setState(() => _typingUser = null);
          });
        }
      },
    );
  }

  Future<void> _sendMessage() async {
    final text = _controller.text.trim();
    if (text.isEmpty) return;

    _controller.clear();

    await _api.sendTextMessage(
      conversationId: widget.conversationId,
      message:        text,
    );
  }

  void _onTyping() {
    _socket.sendTyping(
      widget.conversationId,
      widget.authUserId,
      widget.authUserName,
    );
  }

  @override
  void dispose() {
    _socket.leaveConversation(widget.conversationId);
    _controller.dispose();
    _typingTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Chat')),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              reverse:     true,
              itemCount:   _messages.length,
              itemBuilder: (_, i) => MessageBubble(message: _messages[i]),
            ),
          ),
          if (_typingUser != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text('$_typingUser is typing...'),
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(8),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _controller,
                    onChanged:  (_) => _onTyping(),
                    decoration: const InputDecoration(hintText: 'Type a message'),
                  ),
                ),
                IconButton(icon: const Icon(Icons.send), onPressed: _sendMessage),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

*/
