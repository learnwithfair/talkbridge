<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RahatulRabbi\TalkBridge\Commands\Concerns\PrintsHeader;
use RahatulRabbi\TalkBridge\Support\ComposerRunner;
use RahatulRabbi\TalkBridge\Support\UserModelModifier;

class InstallCommand extends Command
{
    use PrintsHeader;

    protected $signature = 'talkbridge:install
                            {--broadcaster= : Skip prompt — pass reverb|pusher|ably|log|null}
                            {--push=        : Skip prompt — pass none|fcm|web|both}
                            {--force        : Overwrite existing published files}
                            {--no-migrate   : Skip running migrations}';

    protected $description = 'Install TalkBridge — fully automatic, zero manual steps';

    protected ComposerRunner $composer;
    protected string $selectedBroadcaster = '';
    protected string $selectedPush        = '';

    public function handle(): int
    {
        $this->composer = new ComposerRunner();

        $this->printHeader('Installer');

        $this->step(1, 'Publishing assets');
        $this->publishAssets();

        $this->step(2, 'Select broadcasting driver');
        $this->selectedBroadcaster = $this->askBroadcaster();

        $this->step(3, 'Installing broadcaster package');
        $this->installBroadcaster($this->selectedBroadcaster);

        $this->step(4, 'Writing broadcaster .env variables');
        $this->writeBroadcasterEnv($this->selectedBroadcaster);

        $this->step(5, 'Select push notification provider');
        $this->selectedPush = $this->askPushProvider();

        $this->step(6, 'Installing push notification package');
        $this->installPushProvider($this->selectedPush);

        $this->step(7, 'Writing push notification .env variables');
        $this->writePushEnv($this->selectedPush);

        $this->step(8, 'Writing base .env variables');
        $this->writeBaseEnv();

        $this->step(9, 'Saving selections for uninstall');
        $this->saveSelections();

        $this->step(10, 'Patching User model');
        $this->patchUserModel();

        $this->step(11, 'ServiceProvider auto-wiring');
        $this->printAutoWiringSummary();

        if (! $this->option('no-migrate')) {
            $this->step(12, 'Database migrations');
            $this->runMigrations();
        }

        $this->printSuccess();

        return self::SUCCESS;
    }

    // =========================================================================
    // Interactive prompts
    // =========================================================================

    protected function askBroadcaster(): string
    {
        $passed = $this->option('broadcaster');
        $valid  = ['reverb', 'pusher', 'ably', 'log', 'null'];

        if ($passed && in_array($passed, $valid, true)) {
            $this->line("    Using: {$passed}");
            return $passed;
        }

        $this->newLine();

        $index = $this->choice(
            '    Which broadcasting driver do you want to use?',
            [
                'reverb — Reverb (self-hosted WebSocket, recommended)',
                'pusher — Pusher Channels (cloud, requires credentials)',
                'ably   — Ably (cloud, requires API key)',
                'log    — Log driver (testing / local only)',
                'null   — Null driver (broadcasting disabled)',
            ],
            0
        );

        return explode(' ', $index)[0];
    }

    protected function askPushProvider(): string
    {
        $passed = $this->option('push');
        $valid  = ['none', 'fcm', 'web', 'both'];

        if ($passed !== null && in_array($passed, $valid, true)) {
            $this->line("    Using: {$passed}");
            return $passed;
        }

        $this->newLine();

        $index = $this->choice(
            '    Which push notification provider do you want?',
            [
                'none — Disabled (no push notifications)',
                'fcm  — Firebase Cloud Messaging (Android + iOS)',
                'web  — Browser Web Push via VAPID (desktop browsers)',
                'both — FCM + Web Push (mobile and browser)',
            ],
            0
        );

        return explode(' ', $index)[0];
    }

    // =========================================================================
    // Publishing
    // =========================================================================

    protected function publishAssets(): void
    {
        $tags = [
            'talkbridge-config'     => 'config/talkbridge.php',
            'talkbridge-migrations' => 'database/migrations/',
            'talkbridge-stubs'      => 'stubs/talkbridge/',
        ];

        foreach ($tags as $tag => $dest) {
            Artisan::call('vendor:publish', [
                '--tag'   => $tag,
                '--force' => $this->option('force'),
            ]);
            $this->line("    Published  ->  {$dest}");
        }
    }

    // =========================================================================
    // Broadcaster installation
    // =========================================================================

    protected function installBroadcaster(string $driver): void
    {
        $this->line("    Driver: {$driver}");

        match ($driver) {
            'reverb' => $this->installReverb(),
            'pusher' => $this->installPusher(),
            'ably'   => $this->installAbly(),
            default  => $this->line("    No extra package required for '{$driver}'."),
        };
    }

    protected function installReverb(): void
    {
        if ($this->composer->isInstalled(\Laravel\Reverb\ReverbServiceProvider::class)) {
            $this->line('    laravel/reverb already installed.');
            $this->writeBroadcastingConfig('reverb');
            return;
        }

        $this->line('    Running: composer require laravel/reverb ...');
        [$ok] = $this->composer->require('laravel/reverb');

        if (! $ok) {
            $this->warn('    Auto-install failed. Run manually:');
            $this->line('      composer require laravel/reverb');
            return;
        }

        $this->line('    laravel/reverb installed.');
        $this->dumpAutoload();

        $this->line('    Running package:discover...');
        Artisan::call('package:discover', ['--ansi' => true]);

        if (array_key_exists('reverb:install', Artisan::all())) {
            $this->line('    Running reverb:install...');
            Artisan::call('reverb:install', ['--no-interaction' => true]);
            $this->line('    reverb:install done.');
        } else {
            $this->line('    Writing reverb config into config/broadcasting.php...');
            $this->writeBroadcastingConfig('reverb');
        }
    }

    protected function installPusher(): void
    {
        if ($this->composer->isInstalled(\Pusher\Pusher::class)) {
            $this->line('    pusher/pusher-php-server already installed.');
            $this->writeBroadcastingConfig('pusher');
            return;
        }

        $this->line('    Running: composer require pusher/pusher-php-server ...');
        [$ok] = $this->composer->require('pusher/pusher-php-server');

        if ($ok) {
            $this->line('    pusher/pusher-php-server installed.');
            $this->dumpAutoload();
            $this->writeBroadcastingConfig('pusher');
        } else {
            $this->warn('    Auto-install failed. Run manually:');
            $this->line('      composer require pusher/pusher-php-server');
        }
    }

    protected function installAbly(): void
    {
        if ($this->composer->isInstalled(\Ably\AblyRest::class)) {
            $this->line('    ably/ably-php already installed.');
            $this->writeBroadcastingConfig('ably');
            return;
        }

        $this->line('    Running: composer require ably/ably-php ...');
        [$ok] = $this->composer->require('ably/ably-php');

        if ($ok) {
            $this->line('    ably/ably-php installed.');
            $this->dumpAutoload();
            $this->writeBroadcastingConfig('ably');
        } else {
            $this->warn('    Auto-install failed. Run manually:');
            $this->line('      composer require ably/ably-php');
        }
    }

    protected function writeBroadcastingConfig(string $driver): void
    {
        $configPath = config_path('broadcasting.php');

        if (! File::exists($configPath)) {
            Artisan::call('vendor:publish', [
                '--provider'       => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
                '--no-interaction' => true,
            ]);
        }

        if (! File::exists($configPath)) {
            $this->warn('    config/broadcasting.php not found. Add the ' . $driver . ' connection manually.');
            return;
        }

        $content = File::get($configPath);

        if (str_contains($content, "'" . $driver . "'")) {
            $this->line("    {$driver} already present in config/broadcasting.php.");
            return;
        }

        $block = $this->buildBroadcastConnectionBlock($driver);

        if (! $block) {
            return;
        }

        $updated = preg_replace_callback(
            '/(\'connections\'\s*=>\s*\[)(.*?)(\s*\]\s*;)/s',
            fn($matches) => $matches[1] . $matches[2] . $block . $matches[3],
            $content
        );

        if ($updated && $updated !== $content) {
            File::put($configPath, $updated);
            $this->line("    {$driver} connection added to config/broadcasting.php.");
        } else {
            $this->warn("    Could not inject {$driver} into config/broadcasting.php. Add it manually.");
        }
    }

    protected function buildBroadcastConnectionBlock(string $driver): string
    {
        return match ($driver) {
            'reverb' => '
        \'reverb\' => [
            \'driver\'  => \'reverb\',
            \'key\'     => env(\'REVERB_APP_KEY\'),
            \'secret\'  => env(\'REVERB_APP_SECRET\'),
            \'app_id\'  => env(\'REVERB_APP_ID\'),
            \'options\' => [
                \'host\'   => env(\'REVERB_HOST\', \'127.0.0.1\'),
                \'port\'   => env(\'REVERB_PORT\', 8080),
                \'scheme\' => env(\'REVERB_SCHEME\', \'http\'),
                \'useTLS\' => env(\'REVERB_SCHEME\', \'http\') === \'https\',
            ],
            \'client_options\' => [],
        ],',

            'pusher' => '
        \'pusher\' => [
            \'driver\'  => \'pusher\',
            \'app_id\'  => env(\'PUSHER_APP_ID\'),
            \'key\'     => env(\'PUSHER_APP_KEY\'),
            \'secret\'  => env(\'PUSHER_APP_SECRET\'),
            \'cluster\' => env(\'PUSHER_APP_CLUSTER\', \'mt1\'),
            \'options\' => [
                \'cluster\' => env(\'PUSHER_APP_CLUSTER\', \'mt1\'),
                \'useTLS\'  => true,
            ],
        ],',

            'ably' => '
        \'ably\' => [
            \'driver\' => \'ably\',
            \'key\'    => env(\'ABLY_KEY\'),
        ],',

            default => '',
        };
    }

    protected function installPushProvider(string $provider): void
    {
        if ($provider === 'none') {
            $this->line('    Push notifications: disabled.');
            return;
        }

        if (in_array($provider, ['fcm', 'both'], true)) {
            $this->installFcm();
        }

        if (in_array($provider, ['web', 'both'], true)) {
            $this->installWebPush();
        }
    }

    protected function installFcm(): void
    {
        if ($this->composer->isInstalled(\Kreait\Firebase\Factory::class)) {
            $this->line('    kreait/laravel-firebase already installed.');
            return;
        }

        $this->line('    Running: composer require kreait/laravel-firebase ...');
        [$ok] = $this->composer->require('kreait/laravel-firebase');

        if ($ok) {
            $this->line('    kreait/laravel-firebase installed.');
            $this->dumpAutoload();
            Artisan::call('vendor:publish', ['--tag' => 'laravel-firebase']);
            $this->line('    Place credentials at: storage/app/firebase/service-account.json');
        } else {
            $this->warn('    Auto-install failed. Run manually:');
            $this->line('      composer require kreait/laravel-firebase');
        }
    }

    protected function installWebPush(): void
    {
        if ($this->composer->isInstalled(\Minishlink\WebPush\WebPush::class)) {
            $this->line('    minishlink/web-push already installed.');
            return;
        }

        $this->line('    Running: composer require minishlink/web-push ...');
        [$ok] = $this->composer->require('minishlink/web-push');

        if ($ok) {
            $this->line('    minishlink/web-push installed.');
            $this->dumpAutoload();
            $this->line('    Generate VAPID keys: php artisan talkbridge:generate-vapid');
        } else {
            $this->warn('    Auto-install failed. Run manually:');
            $this->line('      composer require minishlink/web-push');
        }
    }

    // =========================================================================
    // .env helpers
    // =========================================================================

    protected function writeBroadcasterEnv(string $driver): void
    {
        $vars = match ($driver) {
            'reverb' => [
                'BROADCAST_DRIVER'     => 'reverb',
                'BROADCAST_CONNECTION' => 'reverb',
                'REVERB_APP_ID'        => 'talkbridge-app',
                'REVERB_APP_KEY'       => 'talkbridge-key',
                'REVERB_APP_SECRET'    => 'talkbridge-secret',
                'REVERB_HOST'          => '127.0.0.1',
                'REVERB_PORT'          => '8080',
                'REVERB_SCHEME'        => 'http',
                'VITE_REVERB_APP_KEY'  => '${REVERB_APP_KEY}',
                'VITE_REVERB_HOST'     => '${REVERB_HOST}',
                'VITE_REVERB_PORT'     => '${REVERB_PORT}',
                'VITE_REVERB_SCHEME'   => '${REVERB_SCHEME}',
            ],
            'pusher' => [
                'BROADCAST_DRIVER'        => 'pusher',
                'BROADCAST_CONNECTION'    => 'pusher',
                'PUSHER_APP_ID'           => '',
                'PUSHER_APP_KEY'          => '',
                'PUSHER_APP_SECRET'       => '',
                'PUSHER_APP_CLUSTER'      => 'mt1',
                'VITE_PUSHER_APP_KEY'     => '${PUSHER_APP_KEY}',
                'VITE_PUSHER_APP_CLUSTER' => '${PUSHER_APP_CLUSTER}',
            ],
            'ably'  => ['BROADCAST_DRIVER' => 'ably', 'BROADCAST_CONNECTION' => 'ably', 'ABLY_KEY' => ''],
            default => ['BROADCAST_DRIVER' => $driver, 'BROADCAST_CONNECTION' => $driver],
        };

        $this->appendEnvVars($vars);
    }

    protected function writePushEnv(string $provider): void
    {
        $vars = ['TALKBRIDGE_PUSH_PROVIDER' => $provider];

        if (in_array($provider, ['web', 'both'], true)) {
            $vars['VAPID_PUBLIC_KEY']  = '';
            $vars['VAPID_PRIVATE_KEY'] = '';
            $vars['VAPID_SUBJECT']     = 'mailto:admin@example.com';
        }

        $this->appendEnvVars($vars);
    }

    protected function writeBaseEnv(): void
    {
        $this->appendEnvVars([
            'TALKBRIDGE_ONLINE_THRESHOLD'  => '2',
            'TALKBRIDGE_ROUTE_PREFIX'      => 'api/v1',
            'TALKBRIDGE_UPLOAD_DISK'       => 'public',
            'TALKBRIDGE_MESSAGE_PATH'      => 'uploads/messages',
            'TALKBRIDGE_GROUP_AVATAR_PATH' => 'uploads/groups/avatars',
            'TALKBRIDGE_QUEUE_CONNECTION'  => 'sync',
            'TALKBRIDGE_QUEUE_NAME'        => 'talkbridge',
            'TALKBRIDGE_CACHE_ENABLED'     => 'true',
            'TALKBRIDGE_CACHE_TTL'         => '300',
            'TALKBRIDGE_INVITE_URL'        => '${APP_URL}/api/v1/accept-invite',
        ]);
    }

    protected function saveSelections(): void
    {
        $this->appendEnvVars([
            'TALKBRIDGE_INSTALLED_BROADCASTER' => $this->selectedBroadcaster,
            'TALKBRIDGE_INSTALLED_PUSH'        => $this->selectedPush,
        ]);

        $this->line("    Saved: TALKBRIDGE_INSTALLED_BROADCASTER={$this->selectedBroadcaster}");
        $this->line("    Saved: TALKBRIDGE_INSTALLED_PUSH={$this->selectedPush}");
    }

    // =========================================================================
    // User model
    // =========================================================================

    protected function patchUserModel(): void
    {
        $path = $this->resolveUserModelPath();

        if (! $path) {
            $this->warn('    User model not found. Add trait manually:');
            $this->warn('      use \\RahatulRabbi\\TalkBridge\\Traits\\HasTalkBridgeFeatures;');
            return;
        }

        $modifier = new UserModelModifier($path);
        $modifier->inject();

        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
        $this->line("    Patched  ->  {$relative}");
        $this->line('      - HasTalkBridgeFeatures injected');
        $this->line('      - last_seen_at added to $fillable (if model uses fillable)');
    }

    protected function printAutoWiringSummary(): void
    {
        $prefix = config('talkbridge.routing.prefix', 'api/v1');
        $this->line('    All registered automatically via TalkBridgeServiceProvider:');
        $this->line('      - Middleware alias  talkbridge.last-seen');
        $this->line('      - Scheduler         talkbridge:auto-unmute (every minute)');
        $this->line('      - Broadcast channels online / user.{id} / conversation.{id}');
        $this->line("      - API routes under  {$prefix}");
        $this->line('    No bootstrap/app.php edits required.');
    }

    protected function runMigrations(): void
    {
        if ($this->confirm('    Run migrations now?', true)) {
            Artisan::call('migrate', [], $this->output);
            $this->line('    Migrations complete.');
        }
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    protected function dumpAutoload(): void
    {
        $this->line('    Rebuilding autoload...');
        (new ComposerRunner())->run('composer dump-autoload --optimize 2>&1');
        $this->line('    Autoload rebuilt.');
    }

    protected function appendEnvVars(array $vars): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('    .env not found — skipping.');
            return;
        }

        $existing = File::get($envPath);

        foreach ($vars as $key => $value) {
            if (! str_contains($existing, $key . '=')) {
                File::append($envPath, "\n{$key}={$value}");
                $this->line("    .env  +  {$key}");
            }
        }
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
        $this->info('  |   Installation complete. Zero manual steps taken.  |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();
        $this->line("  Broadcaster:    {$this->selectedBroadcaster}");
        $this->line("  Push provider:  {$this->selectedPush}");
        $this->newLine();
        $this->line('  Recommended next steps:');
        $this->line('    1. Review config/talkbridge.php  (update user_fields if needed)');

        match ($this->selectedBroadcaster) {
            'reverb' => $this->line('    2. php artisan reverb:start --debug'),
            'pusher' => $this->line('    2. Fill PUSHER_* credentials in .env'),
            'ably'   => $this->line('    2. Fill ABLY_KEY in .env'),
            default  => null,
        };

        if (in_array($this->selectedPush, ['fcm', 'both'])) {
            $this->line('    3. Add storage/app/firebase/service-account.json');
        }
        if (in_array($this->selectedPush, ['web', 'both'])) {
            $this->line('    3. php artisan talkbridge:generate-vapid');
        }

        $this->line('    4. php artisan queue:work --queue=talkbridge');
        $this->newLine();
        $this->line('  To uninstall:  php artisan talkbridge:uninstall');
        $this->newLine();
    }

    protected function step(int $n, string $label): void
    {
        $this->newLine();
        $this->info("  [{$n}] {$label}");
        $this->line('  ' . str_repeat('-', 54));
    }
}