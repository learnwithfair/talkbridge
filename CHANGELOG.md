# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.0] - 2026-01-01

### Added
- Initial release as `rahatulrabbi/talkbridge`
- Fully automatic install and uninstall (zero manual steps)
- Auto-installs broadcaster package (Reverb / Pusher / Ably) on install
- Auto-installs push notification package (FCM / Web Push / both) on install
- Dynamic user name: single column or composite (`first_name` + `last_name` etc.)
- Detects `$fillable` vs `$guarded` and patches User model accordingly
- `HasTalkBridgeFeatures` trait with marker-based injection and removal
- Private and group conversations
- Real-time broadcasting via Reverb and Pusher
- Message reactions, replies, forwarding, pinning
- Message status: sent, delivered, seen
- File attachments with media library
- Group roles: super_admin / admin / member
- Group invite links with expiry and usage limits
- Mute / unmute (timed or indefinite) with auto-unmute scheduler
- User blocking and restricting
- FCM push notifications via kreait/laravel-firebase
- Browser Web Push via minishlink/web-push (VAPID)
- Middleware, scheduler, channels, routes — all auto-registered
- Vue 3, React, Flutter, React Native integration guides
- `php artisan talkbridge:install` — fully automatic installer, zero manual steps.
  Choose broadcaster (Reverb / Pusher / Ably / Log / Null) and push provider (FCM / Web Push / both / none) interactively or via flags
- `php artisan talkbridge:uninstall` — removes all injected code, published files, env vars, and optional packages.
  Use `--keep-data` to skip table removal; `--keep-packages` to skip package removal
- `php artisan talkbridge:update` — runs `composer update`, re-publishes migrations,
  runs new migrations, verifies User model patch, and clears caches.
  Use `--force` to overwrite config and stubs; use `--to=x.x.x` to pin a specific version
- `php artisan talkbridge:version` — shows installed version and recent changelog.
  Use `--check` flag to compare with latest version on Packagist
- `php artisan talkbridge:publish` — publish specific assets (config / migrations / stubs / lang / all).
  Use `--force` to overwrite existing files
- `php artisan talkbridge:generate-vapid` — generates VAPID keys for Web Push and writes them to `.env`
- `php artisan talkbridge:auto-unmute` — scheduler command, runs every minute, auto-unmutes expired mutes
- `ComposerRunner` support class — runs Composer commands from within Artisan commands
- `UserModelModifier` support class — injects and removes `HasTalkBridgeFeatures` trait and `$fillable` columns
- Migration `add_talkbridge_fields_to_users_table`: reads config and adds ONLY missing columns.
  Handles `name` (single or composite), `avatar_path`, `last_seen_at`, `is_active`. Fully idempotent
- `config/talkbridge.php`: fully configurable — user model, user fields, routing prefix, middleware,
  upload disk, file paths, queue, cache, push provider, and broadcasting auth middleware
- `config/talkbridge.php`: `broadcasting.auth_middleware` key (default `null`).
  Set explicitly to override middleware for `/broadcasting/auth`:
  ```php
  'auth_middleware' => ['api', 'auth:sanctum'],  // Sanctum token
  'auth_middleware' => ['api', 'auth:api'],       // JWT / Passport
  'auth_middleware' => ['web', 'auth'],           // session-based
  ```
  Leave `null` to auto-derive from `routing.middleware` (recommended)
- `TalkBridgeServiceProvider`: all routes, channels, middleware alias, and scheduler
  registered automatically — no `bootstrap/app.php` edits required
- `TalkBridgeServiceProvider`: `Broadcast::routes()` registered with configurable middleware
  so `/broadcasting/auth` works correctly with Sanctum token auth from Vue/React/mobile clients
- `TalkBridgeServiceProvider`: try/catch around `Broadcast::routes()` and `require channels.php`
  so `package:discover` never crashes when broadcaster SDK is not yet installed
- `.env` writing: both `BROADCAST_DRIVER` (Laravel 10) and `BROADCAST_CONNECTION` (Laravel 11+)
  written on install for maximum cross-version compatibility
- `writeBroadcastingConfig()`: auto-injects broadcaster connection block (reverb / pusher / ably)
  into `config/broadcasting.php` after package install
- `talkbridge_upload_file()`: stores files and returns a relative storage path (portable across disks)
- `talkbridge_file_url()`: converts relative path to full public URL. Works for local, S3, GCS, R2.
  Returns early if path is empty or already a full URL
- `talkbridge_user_name()`, `talkbridge_user_avatar()`, `talkbridge_user_online()`,
  `talkbridge_user_last_seen()` — null-safe global helpers, return safe defaults when user is null
- `MessageAttachmentResource`: uses `talkbridge_file_url()` for all attachment URLs
- `MessageRepository`: reads file size before `storeAs()` moves the temp file;
  detects file type from original filename
- `ConversationResource`: `group_setting.avatar` returned as full URL; null-safe on all relations
- `ConversationEvent`: `avatar` in broadcast payload returned as full URL
- `MessageResource`: null-safe on sender, replyTo->sender, forwardedFrom->sender
- `HasTalkBridgeFeatures`: null guards on `hasBlocked()`, `isBlockedBy()`, `hasRestricted()`
- `UninstallCommand::dropUserColumns()`: removes all config-driven user columns on uninstall
- `UninstallCommand`: reads `TALKBRIDGE_INSTALLED_BROADCASTER` and `TALKBRIDGE_INSTALLED_PUSH`
  from `.env` to only remove packages that TalkBridge actually installed
- Laravel 13 support (`^13.0`), Sanctum 5.x support (`^5.0`), PHP 8.5 support
- README: comprehensive customization guide — override controllers, extend ChatService,
  extend resources, listen to events, use helpers and trait methods directly
- README: full API endpoint reference table with request body notes
- README: composite name column documentation