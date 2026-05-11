<?php

namespace RahatulRabbi\TalkBridge;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RahatulRabbi\TalkBridge\Commands\AutoUnmuteCommand;
use RahatulRabbi\TalkBridge\Commands\InstallCommand;
use RahatulRabbi\TalkBridge\Commands\PublishCommand;
use RahatulRabbi\TalkBridge\Commands\UninstallCommand;
use RahatulRabbi\TalkBridge\Commands\UpdateCommand;
use RahatulRabbi\TalkBridge\Commands\VersionCommand;
use RahatulRabbi\TalkBridge\Http\Middleware\UpdateLastSeen;

class TalkBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config — always safe in register()
        $this->mergeConfigFrom(
            __DIR__ . '/../config/talkbridge.php',
            'talkbridge'
        );

        // Bind ChatService as singleton
        $this->app->singleton(
            \RahatulRabbi\TalkBridge\Services\ChatService::class
        );
    }

    public function boot(): void
    {
        // Always safe — no external dependencies
        $this->registerPublishables();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'talkbridge');
        $this->registerMiddlewareAlias();
        $this->registerCommands();

        // Wrapped in booted() — runs AFTER all providers have booted
        // This guarantees Route, Broadcast, and Schedule are fully ready
        $this->app->booted(function () {
            $this->registerRoutes();
            $this->registerBroadcastChannels();
            $this->registerScheduler();
        });
    }

    // -------------------------------------------------------------------------

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/talkbridge.php' => config_path('talkbridge.php'),
        ], 'talkbridge-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'talkbridge-migrations');

        $this->publishes([
            __DIR__ . '/../stubs/' => base_path('stubs/talkbridge'),
        ], 'talkbridge-stubs');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/talkbridge'),
        ], 'talkbridge-lang');

        // Publish everything at once
        $this->publishes([
            __DIR__ . '/../config/talkbridge.php' => config_path('talkbridge.php'),
            __DIR__ . '/../database/migrations/'  => database_path('migrations'),
            __DIR__ . '/../stubs/'                => base_path('stubs/talkbridge'),
            __DIR__ . '/../lang'                  => lang_path('vendor/talkbridge'),
        ], 'talkbridge');
    }

    /**
     * Register middleware alias.
     * Safe to call directly in boot() — router is always available.
     */
    protected function registerMiddlewareAlias(): void
    {
        $this->app['router']->aliasMiddleware(
            'talkbridge.last-seen',
            UpdateLastSeen::class
        );
    }

    /**
     * Register Artisan commands.
     * Safe to call directly in boot() — console is always available.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UninstallCommand::class,
                UpdateCommand::class,
                VersionCommand::class,
                PublishCommand::class,
                AutoUnmuteCommand::class,
            ]);
        }
    }

    /**
     * Register API routes.
     * Called inside booted() to guarantee Route facade is ready.
     */
    protected function registerRoutes(): void
    {
        if (! config('talkbridge.routing.enabled', true)) {
            return;
        }

        Route::prefix(config('talkbridge.routing.prefix', 'api/v1'))
            ->middleware($this->resolveMiddleware())
            ->group(__DIR__ . '/../routes/api.php');
    }

    /**
     * Register /broadcasting/auth and load channel definitions.
     * Called inside booted() to guarantee Broadcast facade is ready.
     */
    protected function registerBroadcastChannels(): void
    {
        if (! $this->app->bound('Illuminate\Broadcasting\BroadcastManager')) {
            return;
        }

        // Wrap in try/catch — during `composer require` / `package:discover`
        // the broadcasting driver SDK (Pusher, Ably etc.) may not be installed
        // yet. Without this guard the discovery process crashes with
        // "Class Pusher\Pusher not found".
        try {
            Broadcast::routes([
                'middleware' => $this->resolveBroadcastMiddleware(),
            ]);
        } catch (\Throwable $e) {
            // Broadcasting will work normally after the driver SDK is installed
            // and autoload is rebuilt.
            return;
        }

        $channelsFile = __DIR__ . '/../routes/channels.php';

        if (file_exists($channelsFile)) {
            try {
                require $channelsFile;
            } catch (\Throwable $e) {
                // Channel definitions reference models — skip during discovery.
            }
        }
    }

    /**
     * Resolve the full middleware stack for API routes from config.
     * Falls back to safe defaults if config is not published yet.
     *
     * @return array<string>
     */
    protected function resolveMiddleware(): array
    {
        return config('talkbridge.routing.middleware', [
            'api',
            'auth:sanctum',
            'talkbridge.last-seen',
        ]);
    }

    /**
     * Resolve middleware for /broadcasting/auth.
     *
     * Reads talkbridge.broadcasting.auth_middleware from config.
     * Falls back to stripping talkbridge.* aliases from the API middleware
     * stack — those are not valid for the broadcasting auth endpoint.
     *
     * @return array<string>
     */
    protected function resolveBroadcastMiddleware(): array
    {
        // Explicit override in config — highest priority
        if ($override = config('talkbridge.broadcasting.auth_middleware')) {
            return (array) $override;
        }

        // Derive from API middleware: keep only framework middleware,
        // drop any package-specific aliases (talkbridge.*)
        return collect($this->resolveMiddleware())
            ->reject(fn(string $m) => str_starts_with($m, 'talkbridge.'))
            ->values()
            ->all();
    }

    /**
     * Register scheduler.
     * Called inside booted() to guarantee Schedule is ready.
     */
    protected function registerScheduler(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('talkbridge:auto-unmute')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }
}
