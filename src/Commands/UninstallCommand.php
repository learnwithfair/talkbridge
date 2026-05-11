<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RahatulRabbi\TalkBridge\Commands\Concerns\PrintsHeader;
use RahatulRabbi\TalkBridge\Support\ComposerRunner;
use RahatulRabbi\TalkBridge\Support\UserModelModifier;

class UninstallCommand extends Command
{
    use PrintsHeader;

    protected $signature = 'talkbridge:uninstall
                            {--force          : Skip all confirmation prompts}
                            {--keep-data      : Do not drop database tables}
                            {--keep-packages  : Do not remove installed optional packages}';

    protected $description = 'Uninstall TalkBridge — removes all injected code, files, env vars, and optional packages';

    protected ComposerRunner $composer;

    protected array $baseTalkBridgeEnvKeys = [
        'TALKBRIDGE_ONLINE_THRESHOLD',
        'TALKBRIDGE_ROUTE_PREFIX',
        'TALKBRIDGE_UPLOAD_DISK',
        'TALKBRIDGE_MESSAGE_PATH',
        'TALKBRIDGE_GROUP_AVATAR_PATH',
        'TALKBRIDGE_QUEUE_CONNECTION',
        'TALKBRIDGE_QUEUE_NAME',
        'TALKBRIDGE_CACHE_ENABLED',
        'TALKBRIDGE_CACHE_TTL',
        'TALKBRIDGE_INVITE_URL',
        'TALKBRIDGE_PUSH_PROVIDER',
        'TALKBRIDGE_MAX_FILE_SIZE',
        'TALKBRIDGE_INSTALLED_BROADCASTER',
        'TALKBRIDGE_INSTALLED_PUSH',
        'FIREBASE_CREDENTIALS',
        'VAPID_PUBLIC_KEY',
        'VAPID_PRIVATE_KEY',
        'VAPID_SUBJECT',
    ];

    protected array $broadcasterEnvKeys = [
        'reverb' => [
            'BROADCAST_DRIVER', 'BROADCAST_CONNECTION',
            'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET',
            'REVERB_HOST', 'REVERB_PORT', 'REVERB_SCHEME',
            'VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST',
            'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME',
        ],
        'pusher' => [
            'BROADCAST_DRIVER', 'BROADCAST_CONNECTION',
            'PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET', 'PUSHER_APP_CLUSTER',
            'VITE_PUSHER_APP_KEY', 'VITE_PUSHER_APP_CLUSTER',
        ],
        'ably'  => ['BROADCAST_DRIVER', 'BROADCAST_CONNECTION', 'ABLY_KEY'],
        'log'   => ['BROADCAST_DRIVER', 'BROADCAST_CONNECTION'],
        'null'  => ['BROADCAST_DRIVER', 'BROADCAST_CONNECTION'],
    ];

    protected array $chatTables = [
        'conversation_invites',
        'message_deletions',
        'message_reactions',
        'message_statuses',
        'message_attachments',
        'messages',
        'group_settings',
        'conversation_participants',
        'conversations',
        'device_tokens',
        'user_restricts',
        'user_blocks',
    ];

    protected array $migrationPatterns = [
        'create_conversations_table',
        'create_conversation_participants_table',
        'create_messages_table',
        'create_message_attachments_table',
        'create_message_reactions_table',
        'create_message_statuses_table',
        'create_message_deletions_table',
        'create_group_settings_table',
        'create_conversation_invites_table',
        'create_device_tokens_table',
        'create_user_blocks_table',
        'create_user_restricts_table',
        'add_talkbridge_fields_to_users_table',
        'add_last_seen_at_to_users_table',
    ];

    protected array $optionalPackages = [
        'laravel/reverb'           => \Laravel\Reverb\ReverbServiceProvider::class,
        'pusher/pusher-php-server' => \Pusher\Pusher::class,
        'ably/ably-php'            => \Ably\AblyRest::class,
        'kreait/laravel-firebase'  => \Kreait\Firebase\Factory::class,
        'minishlink/web-push'      => \Minishlink\WebPush\WebPush::class,
    ];

    // Override trait color — uninstall = red
    protected function headerColor(): string
    {
        return 'red';
    }

    public function handle(): int
    {
        $this->composer = new ComposerRunner();

        $this->printHeader('Uninstaller');

        if (! $this->option('force')) {
            if (! $this->confirm('  This will remove all TalkBridge data, files, and injected code. Continue?', false)) {
                $this->line('  Uninstall cancelled.');
                return self::SUCCESS;
            }
        }

        $installedBroadcaster = $this->readEnvValue('TALKBRIDGE_INSTALLED_BROADCASTER');
        $installedPush        = $this->readEnvValue('TALKBRIDGE_INSTALLED_PUSH');

        $this->step(1, 'Restoring User model');
        $this->restoreUserModel();

        if (! $this->option('keep-data')) {
            $this->step(2, 'Dropping database tables');
            $this->dropTables();
        } else {
            $this->line('  [2] Skipping table removal (--keep-data)');
        }

        $this->step(3, 'Removing published files');
        $this->removePublishedFiles();

        $this->step(4, 'Removing published migrations');
        $this->removeMigrationFiles();

        $this->step(5, 'Cleaning .env variables');
        $this->cleanEnvVariables($installedBroadcaster);

        if (! $this->option('keep-packages')) {
            $this->step(6, 'Removing optional packages');
            $this->removeOptionalPackages($installedBroadcaster, $installedPush);
        } else {
            $this->line('  [6] Skipping package removal (--keep-packages)');
        }

        $this->printSuccess();

        return self::SUCCESS;
    }

    // =========================================================================
    // Steps
    // =========================================================================

    protected function restoreUserModel(): void
    {
        $path = $this->resolveUserModelPath();

        if (! $path) {
            $this->warn('    User model not found — skipping.');
            return;
        }

        $modifier = new UserModelModifier($path);

        if (! $modifier->isAlreadyInjected()) {
            $this->line('    HasTalkBridgeFeatures not found in User model — nothing to remove.');
            return;
        }

        $modifier->remove();

        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
        $this->line("    Restored  ->  {$relative}");
        $this->line('      - HasTalkBridgeFeatures removed');
        $this->line('      - last_seen_at removed from $fillable (if it was added)');
    }

    protected function dropTables(): void
    {
        foreach ($this->chatTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->line("    Dropped  ->  {$table}");
            }
        }

        $this->dropUserColumns();
    }

    protected function dropUserColumns(): void
    {
        $toDrop = [];

        $lastSeen = config('talkbridge.user_fields.last_seen', 'last_seen_at');
        if ($lastSeen && Schema::hasColumn('users', $lastSeen)) {
            $toDrop[] = $lastSeen;
        }

        $avatar = config('talkbridge.user_fields.avatar');
        if ($avatar && Schema::hasColumn('users', $avatar)) {
            $toDrop[] = $avatar;
        }

        $isActive = config('talkbridge.user_fields.is_active');
        if ($isActive && Schema::hasColumn('users', $isActive)) {
            $toDrop[] = $isActive;
        }

        $nameConfig = config('talkbridge.user_fields.name', 'name');
        if (is_array($nameConfig)) {
            foreach ($nameConfig as $col) {
                if ($col && $col !== 'name' && Schema::hasColumn('users', $col)) {
                    $toDrop[] = $col;
                }
            }
        } elseif ($nameConfig && $nameConfig !== 'name' && Schema::hasColumn('users', $nameConfig)) {
            $toDrop[] = $nameConfig;
        }

        $toDrop = array_unique($toDrop);

        if (! empty($toDrop)) {
            Schema::table('users', fn($t) => $t->dropColumn($toDrop));
            foreach ($toDrop as $col) {
                $this->line("    Removed column  ->  users.{$col}");
            }
        }
    }

    protected function removePublishedFiles(): void
    {
        $targets = [
            config_path('talkbridge.php'),
            base_path('stubs/talkbridge'),
            lang_path('vendor/talkbridge'),
        ];

        foreach ($targets as $path) {
            if (File::exists($path)) {
                File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
                $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
                $this->line("    Removed  ->  {$relative}");
            }
        }
    }

    protected function removeMigrationFiles(): void
    {
        if (! is_dir(database_path('migrations'))) {
            return;
        }

        $files   = File::files(database_path('migrations'));
        $removed = 0;

        foreach ($files as $file) {
            foreach ($this->migrationPatterns as $pattern) {
                if (str_contains($file->getFilename(), $pattern)) {
                    File::delete($file->getPathname());
                    $this->line("    Removed  ->  database/migrations/{$file->getFilename()}");
                    $removed++;
                    break;
                }
            }
        }

        if ($removed === 0) {
            $this->line('    No TalkBridge migration files found.');
        }
    }

    protected function cleanEnvVariables(string $installedBroadcaster): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('    .env not found — skipping.');
            return;
        }

        $broadcasterKeys = $installedBroadcaster && isset($this->broadcasterEnvKeys[$installedBroadcaster])
            ? $this->broadcasterEnvKeys[$installedBroadcaster]
            : ['BROADCAST_DRIVER', 'BROADCAST_CONNECTION'];

        $allKeys = array_unique(array_merge($this->baseTalkBridgeEnvKeys, $broadcasterKeys));

        $content = File::get($envPath);
        $removed = 0;

        foreach ($allKeys as $key) {
            if (str_contains($content, $key . '=')) {
                $content = preg_replace("/^{$key}=.*\r?\n?/m", '', $content);
                $removed++;
            }
        }

        $content = preg_replace("/\n{3,}/", "\n\n", $content);
        File::put($envPath, $content);

        $this->line("    Removed {$removed} variable(s) from .env");
    }

    protected function removeOptionalPackages(string $installedBroadcaster, string $installedPush): void
    {
        $toRemove = [];

        match ($installedBroadcaster) {
            'reverb' => $toRemove[] = 'laravel/reverb',
            'pusher' => $toRemove[] = 'pusher/pusher-php-server',
            'ably'   => $toRemove[] = 'ably/ably-php',
            default  => null,
        };

        if (in_array($installedPush, ['fcm', 'both'])) {
            $toRemove[] = 'kreait/laravel-firebase';
        }
        if (in_array($installedPush, ['web', 'both'])) {
            $toRemove[] = 'minishlink/web-push';
        }

        if (empty($toRemove)) {
            $this->line('    No optional packages to remove.');
            return;
        }

        foreach ($toRemove as $package) {
            $checkClass = $this->optionalPackages[$package] ?? null;

            if ($checkClass && ! $this->composer->isInstalled($checkClass)) {
                $this->line("    Not installed, skipping: {$package}");
                continue;
            }

            $confirmed = $this->option('force')
                || $this->confirm("    Remove {$package}?", true);

            if (! $confirmed) {
                $this->line("    Skipped: {$package}");
                continue;
            }

            $this->line("    Removing {$package}...");
            [$ok] = $this->composer->remove($package);

            if ($ok) {
                $this->line("    Removed  ->  {$package}");
            } else {
                $this->warn("    Failed to remove {$package}. Remove manually:");
                $this->line("      composer remove {$package}");
            }
        }
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    protected function readEnvValue(string $key): string
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return '';
        }

        $content = File::get($envPath);

        if (preg_match("/^{$key}=(.*)$/m", $content, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    protected function resolveUserModelPath(): ?string
    {
        $userModel    = config('talkbridge.user_model', 'App\\Models\\User');
        $relativePath = ltrim(str_replace(['App\\', '\\'], ['app/', '/'], $userModel), '/') . '.php';
        $fullPath     = base_path($relativePath);

        if (File::exists($fullPath)) {
            return $fullPath;
        }

        foreach ([app_path('Models/User.php'), app_path('User.php')] as $fallback) {
            if (File::exists($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    // =========================================================================
    // Footer
    // =========================================================================

    protected function printSuccess(): void
    {
        $this->newLine();
        $this->line('  +----------------------------------------------------+');
        $this->info('  |   Uninstall complete.                              |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();
        $this->line('  Removed:');
        $this->line('    - HasTalkBridgeFeatures from User model');
        $this->line('    - Database tables (unless --keep-data was used)');
        $this->line('    - config/talkbridge.php, migrations, stubs, lang');
        $this->line('    - .env variables (only those TalkBridge added)');
        $this->line('    - Optional packages (only those TalkBridge installed)');
        $this->newLine();
        $this->line('  To fully remove TalkBridge:');
        $this->line('    <comment>composer remove rahatulrabbi/talkbridge</comment>');
        $this->newLine();
    }

    protected function step(int $n, string $label): void
    {
        $this->newLine();
        $this->info("  [{$n}] {$label}");
        $this->line('  ' . str_repeat('-', 54));
    }
}