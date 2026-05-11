// ============================================================
// Laravel Chat — React Native Integration
// ============================================================
//
// Dependencies — add to package.json:
//
//   "@pusher/pusher-websocket-react-native": "^2.0.0"
//   "axios": "^1.6.0"
//   "@react-native-async-storage/async-storage": "^1.21.0"
//   "@react-native-firebase/app": "^18.0.0"         # push notifications
//   "@react-native-firebase/messaging": "^18.0.0"   # push notifications
//
// Run:
//   npm install
//   npx pod-install   # iOS only
// ============================================================


// ============================================================
// src/config/chatConfig.ts
// ============================================================

export const ChatConfig = {
  baseUrl:       'https://your-app.com/api/v1',
  reverbHost:    'your-app.com',
  reverbPort:    443,
  reverbScheme:  'https',
  reverbAppKey:  'your-reverb-app-key',
  forceTLS:      true,
} as const;


// ============================================================
// src/types/chat.ts
// ============================================================

export interface Sender {
  id:   number;
  name: string;
}

export interface MessageAttachment {
  id:    number;
  type:  string;
  path:  string;
  name?: string;
  size?: number;
}

export interface ChatMessage {
  id:                    number;
  conversation_id:       number;
  sender:                Sender;
  message:               string | null;
  message_type:          string;
  is_deleted_for_everyone: boolean;
  is_pinned:             boolean;
  is_mine:               boolean;
  reactions:             { reactions: Record<string, number>; total: number };
  attachments:           MessageAttachment[];
  statuses:              MessageStatus[];
  reply:                 ReplyInfo | null;
  forward:               ForwardInfo | null;
  created_at:            string;
  updated_at:            string;
}

export interface MessageStatus {
  user_id:  number;
  name:     string;
  status:   'sent' | 'delivered' | 'seen';
}

export interface ReplyInfo {
  id:      number;
  sender:  Sender;
  message: string | null;
  type:    string;
}

export interface ForwardInfo {
  id:      number;
  sender:  Sender;
  message: string | null;
  type:    string;
}

export interface Conversation {
  id:           number;
  type:         'private' | 'group';
  name:         string | null;
  is_muted:     boolean;
  is_blocked:   boolean;
  is_online:    boolean;
  unread_count: number;
  role:         string | null;
  last_message: LastMessage | null;
  receiver:     ConversationReceiver | null;
  group_setting: GroupSetting | null;
}

export interface LastMessage {
  id:         number;
  message:    string | null;
  created_at: string;
}

export interface ConversationReceiver {
  id:          number;
  name:        string;
  avatar_path: string | null;
  is_online:   boolean;
  last_seen:   string | null;
}

export interface GroupSetting {
  allow_members_to_send_messages:           boolean;
  allow_members_to_add_remove_participants: boolean;
  allow_members_to_change_group_info:       boolean;
  allow_invite_users_via_link:              boolean;
}


// ============================================================
// src/services/chatApi.ts
// ============================================================

import axios, { AxiosInstance } from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { ChatConfig } from '../config/chatConfig';
import type { ChatMessage, Conversation } from '../types/chat';

class ChatApiService {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({ baseURL: ChatConfig.baseUrl });

    // Attach auth token to every request
    this.client.interceptors.request.use(async (config) => {
      const token = await AsyncStorage.getItem('auth_token');
      if (token) config.headers.Authorization = `Bearer ${token}`;
      config.headers.Accept = 'application/json';
      return config;
    });
  }

  // ------------------------------------------------------------------
  // Conversations
  // ------------------------------------------------------------------

  async getConversations(page = 1, search?: string): Promise<Conversation[]> {
    const { data } = await this.client.get('/conversations', {
      params: { page, ...(search ? { q: search } : {}) },
    });
    return data.data;
  }

  async startPrivateConversation(receiverId: number): Promise<Conversation> {
    const { data } = await this.client.post('/conversations/private', { receiver_id: receiverId });
    return data.data;
  }

  async createGroup(params: {
    name:           string;
    participantIds: number[];
    description?:   string;
    groupType?:     string;
  }): Promise<Conversation> {
    const { data } = await this.client.post('/conversations', {
      name:         params.name,
      participants: params.participantIds,
      group: {
        description: params.description,
        type:        params.groupType ?? 'private',
      },
    });
    return data.data;
  }

  async deleteConversation(conversationId: number): Promise<void> {
    await this.client.delete(`/conversations/${conversationId}`);
  }

  // ------------------------------------------------------------------
  // Messages
  // ------------------------------------------------------------------

  async getMessages(
    conversationId: number,
    page    = 1,
    perPage = 20,
    search?: string,
  ): Promise<ChatMessage[]> {
    const { data } = await this.client.get(`/messages/${conversationId}`, {
      params: { page, per_page: perPage, ...(search ? { q: search } : {}) },
    });
    return data.data;
  }

  async sendTextMessage(params: {
    conversationId:    number;
    message:           string;
    messageType?:      string;
    replyToMessageId?: number;
  }): Promise<ChatMessage> {
    const { data } = await this.client.post('/messages', {
      conversation_id:      params.conversationId,
      message:              params.message,
      message_type:         params.messageType ?? 'text',
      reply_to_message_id:  params.replyToMessageId,
    });
    return data.data;
  }

  async sendFileMessage(params: {
    conversationId: number;
    fileUri:        string;
    fileName:       string;
    mimeType:       string;
    messageType:    string;
  }): Promise<ChatMessage> {
    const form = new FormData();
    form.append('conversation_id', String(params.conversationId));
    form.append('message_type',    params.messageType);
    form.append('attachments[0][path]', {
      uri:  params.fileUri,
      name: params.fileName,
      type: params.mimeType,
    } as any);

    const { data } = await this.client.post('/messages', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.data;
  }

  async deleteForMe(messageIds: number[]): Promise<void> {
    await this.client.delete('/messages/delete-for-me', {
      data: { message_ids: messageIds },
    });
  }

  async deleteForEveryone(messageIds: number[]): Promise<void> {
    await this.client.delete('/messages/delete-for-everyone', {
      data: { message_ids: messageIds },
    });
  }

  async forwardMessage(messageId: number, conversationIds: number[]): Promise<void> {
    await this.client.post(`/messages/${messageId}/forward`, { conversation_ids: conversationIds });
  }

  async pinToggle(messageId: number): Promise<void> {
    await this.client.post(`/messages/${messageId}/toggle-pin`);
  }

  async markAsSeen(conversationId: number): Promise<void> {
    await this.client.get(`/messages/seen/${conversationId}`);
  }

  async markSpecificSeen(conversationId: number, messageIds: number[]): Promise<void> {
    await this.client.post('/messages/mark-seen', {
      conversation_id: conversationId,
      message_ids:     messageIds,
    });
  }

  async markDelivered(conversationId: number): Promise<void> {
    await this.client.get(`/messages/delivered/${conversationId}`);
  }

  // ------------------------------------------------------------------
  // Reactions
  // ------------------------------------------------------------------

  async toggleReaction(messageId: number, reaction: string): Promise<void> {
    await this.client.post(`/messages/${messageId}/reaction`, { reaction });
  }

  // ------------------------------------------------------------------
  // Group management
  // ------------------------------------------------------------------

  async addMembers(conversationId: number, memberIds: number[]): Promise<void> {
    await this.client.post(`/group/${conversationId}/members/add`, { member_ids: memberIds });
  }

  async removeMembers(conversationId: number, memberIds: number[]): Promise<void> {
    await this.client.post(`/group/${conversationId}/members/remove`, { member_ids: memberIds });
  }

  async muteGroup(conversationId: number, minutes = -1): Promise<void> {
    await this.client.post(`/group/${conversationId}/mute`, { minutes });
  }

  async leaveGroup(conversationId: number): Promise<void> {
    await this.client.post(`/group/${conversationId}/leave`);
  }

  // ------------------------------------------------------------------
  // Users
  // ------------------------------------------------------------------

  async blockToggle(userId: number): Promise<void> {
    await this.client.post(`/users/${userId}/block-toggle`);
  }

  async restrictToggle(userId: number): Promise<void> {
    await this.client.post(`/users/${userId}/restrict-toggle`);
  }

  // ------------------------------------------------------------------
  // Device token
  // ------------------------------------------------------------------

  async registerDeviceToken(token: string, platform: 'android' | 'ios'): Promise<void> {
    await this.client.post('/device-tokens', { token, platform });
  }
}

export const chatApi = new ChatApiService();


// ============================================================
// src/services/chatSocket.ts
// ============================================================

import { Pusher, PusherEvent, PusherMember } from '@pusher/pusher-websocket-react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { ChatConfig } from '../config/chatConfig';

type MessageHandler      = (payload: Record<string, any>) => void;
type ConversationHandler = (action: string, conversation: Record<string, any>) => void;
type TypingHandler       = (data: Record<string, any>) => void;
type PresenceHandler     = (users: any[]) => void;

class ChatSocketService {
  private pusher: Pusher;
  private initialized = false;

  constructor() {
    this.pusher = Pusher.getInstance();
  }

  async init(): Promise<void> {
    if (this.initialized) return;

    const token = await AsyncStorage.getItem('auth_token');

    await this.pusher.init({
      apiKey:   ChatConfig.reverbAppKey,
      cluster:  'mt1',
      wsHost:   ChatConfig.reverbHost,
      wsPort:   ChatConfig.reverbPort,
      wssPort:  ChatConfig.reverbPort,
      useTLS:   ChatConfig.forceTLS,
      authEndpoint: `${ChatConfig.baseUrl.replace('/api/v1', '')}/broadcasting/auth`,
      onAuthorizer: async (channelName: string, socketId: string) => {
        const t = await AsyncStorage.getItem('auth_token');
        const res = await fetch(
          `${ChatConfig.baseUrl.replace('/api/v1', '')}/broadcasting/auth`,
          {
            method:  'POST',
            headers: {
              'Authorization': `Bearer ${t}`,
              'Content-Type':  'application/json',
              'Accept':        'application/json',
            },
            body: JSON.stringify({ socket_id: socketId, channel_name: channelName }),
          }
        );
        return res.json();
      },
    });

    await this.pusher.connect();
    this.initialized = true;
  }

  async disconnect(): Promise<void> {
    await this.pusher.disconnect();
    this.initialized = false;
  }

  // ------------------------------------------------------------------
  // Online presence
  // ------------------------------------------------------------------

  async subscribeOnline(onHere: PresenceHandler): Promise<void> {
    await this.pusher.subscribe({
      channelName:           'presence-online',
      onSubscriptionSucceeded: (_: string, data: any) => {
        const members = Object.values(data?.presence?.hash ?? {});
        onHere(members as any[]);
      },
    });
  }

  // ------------------------------------------------------------------
  // User private channel
  // ------------------------------------------------------------------

  async subscribeUser(userId: number, onConversationEvent: ConversationHandler): Promise<void> {
    await this.pusher.subscribe({
      channelName: `private-user.${userId}`,
      onEvent: (event: PusherEvent) => {
        if (event.eventName === 'ConversationEvent') {
          const data = this.parse(event.data);
          onConversationEvent(data.action, data.conversation ?? {});
        }
      },
    });
  }

  // ------------------------------------------------------------------
  // Conversation channel
  // ------------------------------------------------------------------

  async subscribeConversation(params: {
    conversationId:    number;
    onMessageEvent:    MessageHandler;
    onConversationEvent: ConversationHandler;
    onTyping?:         TypingHandler;
    onHere?:           PresenceHandler;
    onJoining?:        (user: any) => void;
    onLeaving?:        (user: any) => void;
  }): Promise<void> {
    const { conversationId } = params;

    await this.pusher.subscribe({
      channelName: `presence-conversation.${conversationId}`,

      onSubscriptionSucceeded: (_: string, data: any) => {
        const members = Object.values(data?.presence?.hash ?? {});
        params.onHere?.(members as any[]);
      },

      onMemberAdded:   (member: PusherMember) => params.onJoining?.(member),
      onMemberRemoved: (member: PusherMember) => params.onLeaving?.(member),

      onEvent: (event: PusherEvent) => {
        if (event.eventName === 'MessageEvent') {
          params.onMessageEvent(this.parse(event.data));
        }
        if (event.eventName === 'ConversationEvent') {
          const data = this.parse(event.data);
          params.onConversationEvent(data.action, data.conversation ?? {});
        }
        if (event.eventName === 'client-typing') {
          params.onTyping?.(this.parse(event.data));
        }
      },
    });
  }

  async sendTyping(conversationId: number, userId: number, userName: string): Promise<void> {
    await this.pusher.trigger({
      channelName: `presence-conversation.${conversationId}`,
      eventName:   'client-typing',
      data:        { userId, name: userName, isTyping: true },
    });
  }

  async leaveConversation(conversationId: number): Promise<void> {
    await this.pusher.unsubscribe({ channelName: `presence-conversation.${conversationId}` });
  }

  async leaveUser(userId: number): Promise<void> {
    await this.pusher.unsubscribe({ channelName: `private-user.${userId}` });
  }

  private parse(raw: any): Record<string, any> {
    if (typeof raw === 'string') return JSON.parse(raw);
    if (typeof raw === 'object') return raw;
    return {};
  }
}

export const chatSocket = new ChatSocketService();


// ============================================================
// src/hooks/useChat.ts
// ============================================================

import { useCallback, useEffect, useRef, useState } from 'react';
import { chatApi }    from '../services/chatApi';
import { chatSocket } from '../services/chatSocket';
import type { ChatMessage } from '../types/chat';

export function useChat(conversationId: number, userId: number, userName: string) {
  const [messages,   setMessages]   = useState<ChatMessage[]>([]);
  const [loading,    setLoading]    = useState(true);
  const [typingUser, setTypingUser] = useState<string | null>(null);
  const typingTimer                 = useRef<ReturnType<typeof setTimeout> | null>(null);

  // ------------------------------------------------------------------
  // Load initial messages
  // ------------------------------------------------------------------

  const loadMessages = useCallback(async () => {
    setLoading(true);
    try {
      const msgs = await chatApi.getMessages(conversationId);
      setMessages(msgs);
      await chatApi.markAsSeen(conversationId);
      await chatApi.markDelivered(conversationId);
    } finally {
      setLoading(false);
    }
  }, [conversationId]);

  // ------------------------------------------------------------------
  // Subscribe to real-time events
  // ------------------------------------------------------------------

  useEffect(() => {
    loadMessages();

    chatSocket.subscribeConversation({
      conversationId,

      onMessageEvent: (data) => {
        const { type, payload } = data;

        setMessages(prev => {
          if (type === 'sent') {
            return [payload as ChatMessage, ...prev];
          }
          if (type === 'updated' || type === 'deleted_for_everyone') {
            return prev.map(m => m.id === (payload as ChatMessage).id ? payload as ChatMessage : m);
          }
          if (type === 'reaction') {
            return prev.map(m =>
              m.id === payload.message_id
                ? { ...m, reactions: payload.reactions }
                : m
            );
          }
          return prev;
        });
      },

      onConversationEvent: (action) => {
        if (action === 'deleted') {
          // Navigate back
        }
      },

      onTyping: ({ name, isTyping }) => {
        if (isTyping && name !== userName) {
          setTypingUser(name);
          if (typingTimer.current) clearTimeout(typingTimer.current);
          typingTimer.current = setTimeout(() => setTypingUser(null), 3000);
        }
      },
    });

    return () => {
      chatSocket.leaveConversation(conversationId);
    };
  }, [conversationId]);

  // ------------------------------------------------------------------
  // Actions
  // ------------------------------------------------------------------

  const sendMessage = useCallback(async (text: string, replyToId?: number) => {
    if (! text.trim()) return;
    await chatApi.sendTextMessage({
      conversationId,
      message:          text,
      replyToMessageId: replyToId,
    });
  }, [conversationId]);

  const sendTyping = useCallback(() => {
    chatSocket.sendTyping(conversationId, userId, userName);
  }, [conversationId, userId, userName]);

  const deleteMessage = useCallback(async (messageId: number, forEveryone = false) => {
    if (forEveryone) {
      await chatApi.deleteForEveryone([messageId]);
    } else {
      await chatApi.deleteForMe([messageId]);
      setMessages(prev => prev.filter(m => m.id !== messageId));
    }
  }, []);

  const reactToMessage = useCallback(async (messageId: number, reaction: string) => {
    await chatApi.toggleReaction(messageId, reaction);
  }, []);

  return {
    messages,
    loading,
    typingUser,
    sendMessage,
    sendTyping,
    deleteMessage,
    reactToMessage,
    reload: loadMessages,
  };
}


// ============================================================
// src/services/pushNotificationService.ts
// ============================================================

import messaging from '@react-native-firebase/messaging';
import { Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { chatApi } from './chatApi';

export async function initPushNotifications(): Promise<void> {
  // Request permission (iOS)
  const authStatus = await messaging().requestPermission();
  const enabled = (
    authStatus === messaging.AuthorizationStatus.AUTHORIZED ||
    authStatus === messaging.AuthorizationStatus.PROVISIONAL
  );

  if (! enabled) return;

  // Register token with backend
  const token    = await messaging().getToken();
  const platform = Platform.OS as 'android' | 'ios';

  if (token) {
    await chatApi.registerDeviceToken(token, platform);
    await AsyncStorage.setItem('fcm_token', token);
  }

  // Refresh token
  messaging().onTokenRefresh(async (newToken) => {
    await chatApi.registerDeviceToken(newToken, platform);
    await AsyncStorage.setItem('fcm_token', newToken);
  });

  // Foreground notification
  messaging().onMessage(async (remoteMessage) => {
    const conversationId = remoteMessage.data?.conversation_id;
    // Display in-app banner or update chat list badge
    // Example: chatEventBus.emit('push_received', { conversationId });
  });

  // Background / quit tap → navigate to conversation
  messaging().onNotificationOpenedApp((remoteMessage) => {
    const conversationId = remoteMessage.data?.conversation_id;
    if (conversationId) {
      // navigate('Chat', { conversationId: Number(conversationId) });
    }
  });

  // Quit state tap
  messaging()
    .getInitialNotification()
    .then((remoteMessage) => {
      if (remoteMessage) {
        const conversationId = remoteMessage.data?.conversation_id;
        if (conversationId) {
          // navigate('Chat', { conversationId: Number(conversationId) });
        }
      }
    });
}
