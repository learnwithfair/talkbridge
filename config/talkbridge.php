<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model'               => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | User Field Mapping
    |--------------------------------------------------------------------------
    | Map your User model columns to TalkBridge's expected field names.
    | Supports composite name fields: set 'name' to an array for multi-column names.
    |
    | Examples:
    |   Single column:   'name' => 'name'
    |   Two columns:     'name' => ['first_name', 'last_name']
    |   Three columns:   'name' => ['f_name', 'm_name', 'l_name']
    |
    */
    'user_fields'              => [
        'id'        => 'id',
        'name'      => 'name',         // or ['first_name', 'last_name']
        'avatar'    => 'avatar_path',  // change to your avatar column
        'last_seen' => 'last_seen_at', // change to your last_seen column
        'is_active' => null,           // set to 'is_active' if your table has it
    ],

    /*
    |--------------------------------------------------------------------------
    | Online Threshold
    |--------------------------------------------------------------------------
    | Minutes after last_seen before a user is considered offline.
    |
    */
    'online_threshold_minutes' => env('TALKBRIDGE_ONLINE_THRESHOLD', 2),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------

         | Middleware for API routes.
         |
         | Override explicitly when your auth setup differs from your API routes:
         |   'auth_middleware' => ['api', 'auth:sanctum', 'talkbridge.last-seen],
         |   'auth_middleware' => ['api', 'auth:api', 'talkbridge.last-seen],      // JWT example
         |   'auth_middleware' => ['web', 'auth', 'talkbridge.last-seen],          // session example
    */
    'routing'                  => [
        'enabled'    => true,
        'prefix'     => env('TALKBRIDGE_ROUTE_PREFIX', 'api/v1'),
        'middleware' => ['api', 'auth:sanctum', 'talkbridge.last-seen'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    | Supported drivers: "reverb", "pusher", "ably", "log", "null"
    |
    */
    'broadcasting'             => [
        'driver'          => env('BROADCAST_DRIVER', 'reverb'),

        'reverb'          => [
            'app_id' => env('REVERB_APP_ID'),
            'key'    => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'host'   => env('REVERB_HOST', '127.0.0.1'),
            'port'   => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],

        'pusher'          => [
            'app_id'  => env('PUSHER_APP_ID'),
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'use_tls' => env('PUSHER_SCHEME', 'https') === 'https',
        ],

        /*
         | Middleware for /broadcasting/auth endpoint.
         |
         | Leave null to auto-derive from routing.middleware (recommended).
         | The ServiceProvider strips talkbridge.* aliases automatically since
         | they are not valid on the broadcasting auth endpoint.
         |
         | Override explicitly when your auth setup differs from your API routes:
         |   'auth_middleware' => ['api', 'auth:sanctum'],
         |   'auth_middleware' => ['api', 'auth:api'],      // JWT example
         |   'auth_middleware' => ['web', 'auth'],          // session example
         */
        'auth_middleware' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notifications
    |--------------------------------------------------------------------------
    | Supported providers: "none", "fcm", "web", "both"
    |
    |   none  — no push notifications
    |   fcm   — Firebase Cloud Messaging (mobile: Android + iOS)
    |   web   — Browser Web Push via VAPID (desktop browsers)
    |   both  — FCM + Web Push together
    |
    | FCM requires: kreait/laravel-firebase
    | Web Push requires: minishlink/web-push
    |
    */
    'push_notifications'       => [
        'provider' => env('TALKBRIDGE_PUSH_PROVIDER', 'none'),

        'fcm'      => [
            'credentials_file' => env(
                'FIREBASE_CREDENTIALS',
                storage_path('app/firebase/service-account.json')
            ),
        ],

        'web_push' => [
            'vapid_public_key'  => env('VAPID_PUBLIC_KEY', ''),
            'vapid_private_key' => env('VAPID_PRIVATE_KEY', ''),
            'subject'           => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Uploads
    |--------------------------------------------------------------------------
    |
    | disk — any Laravel filesystem disk defined in config/filesystems.php.
    |
    | Examples:
    |   'disk' => 'public'       local public storage (default)
    |   'disk' => 's3'           Amazon S3
    |   'disk' => 'gcs'          Google Cloud Storage
    |   'disk' => 'r2'           Cloudflare R2
    |   'disk' => 'do_spaces'    DigitalOcean Spaces
    |
    | TalkBridge automatically:
    |   - Sets visibility to "public" for cloud disks (S3, GCS, R2, etc.)
    |   - Generates correct public URLs via Storage::disk($disk)->url()
    |   - Converts relative paths to full URLs in API responses
    |   - Strips base URL when deleting (accepts full URL or relative path)
    |
    */
    'uploads'                  => [
        'disk'              => env('TALKBRIDGE_UPLOAD_DISK', 'public'),
        'message_path'      => env('TALKBRIDGE_MESSAGE_PATH', 'uploads/messages'),
        'group_avatar_path' => env('TALKBRIDGE_GROUP_AVATAR_PATH', 'uploads/groups/avatars'),
        'max_file_size_kb'  => env('TALKBRIDGE_MAX_FILE_SIZE', 51200),
        'allowed_types'     => [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'video' => ['mp4', 'mov', 'avi', 'mkv', 'webm'],
            'audio' => ['mp3', 'wav', 'ogg'],
            'file'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invite URL
    |--------------------------------------------------------------------------
    */
    'invite_url'               => env('TALKBRIDGE_INVITE_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    */
    'pagination'               => [
        'conversations' => 30,
        'messages'      => 20,
        'pinned'        => 40,
        'members'       => 50,
        'media'         => 30,
        'users'         => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Settings
    |--------------------------------------------------------------------------
    */
    'messages'                 => [
        'unsent_placeholder' => 'Unsent',
        'allow_edit'         => true,
        'allow_forward'      => true,
        'allow_pin'          => true,
        'allow_reactions'    => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Group Defaults
    |--------------------------------------------------------------------------
    */
    'group_defaults'           => [
        'allow_members_to_send_messages'           => true,
        'allow_members_to_add_remove_participants' => false,
        'allow_members_to_change_group_info'       => false,
        'admins_must_approve_new_members'          => false,
        'allow_invite_users_via_link'              => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache'                    => [
        'enabled' => env('TALKBRIDGE_CACHE_ENABLED', true),
        'ttl'     => env('TALKBRIDGE_CACHE_TTL', 300),
        'prefix'  => 'talkbridge',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */
    'queue'                    => [
        'connection' => env('TALKBRIDGE_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name'       => env('TALKBRIDGE_QUEUE_NAME', 'talkbridge'),
    ],

];
