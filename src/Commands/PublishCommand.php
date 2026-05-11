<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RahatulRabbi\TalkBridge\Commands\Concerns\PrintsHeader;

class PublishCommand extends Command
{
    use PrintsHeader;

    protected $signature   = 'talkbridge:publish {--tag= : config|migrations|stubs|lang|all} {--force}';
    protected $description = 'Publish specific TalkBridge assets';

    protected array $tags = [
        'config'     => 'talkbridge-config',
        'migrations' => 'talkbridge-migrations',
        'stubs'      => 'talkbridge-stubs',
        'lang'       => 'talkbridge-lang',
        'all'        => 'talkbridge',
    ];

    protected array $destinations = [
        'config'     => 'config/talkbridge.php',
        'migrations' => 'database/migrations/',
        'stubs'      => 'stubs/talkbridge/',
        'lang'       => 'lang/vendor/talkbridge/en/messages.php',
        'all'        => 'config/ + migrations/ + stubs/ + lang/',
    ];

    public function handle(): int
    {
        $this->printHeader('Publisher');

        $tag = $this->option('tag') ?? $this->choice(
            '  Which assets do you want to publish?',
            array_keys($this->tags),
            'all'
        );

        if (! array_key_exists($tag, $this->tags)) {
            $this->error("  Unknown tag: {$tag}");
            return self::FAILURE;
        }

        $this->newLine();
        $this->line("  Publishing  <fg=yellow>{$tag}</> ...");
        $this->line('  ' . str_repeat('-', 54));

        Artisan::call('vendor:publish', [
            '--tag'   => $this->tags[$tag],
            '--force' => $this->option('force'),
        ]);

        $dest = $this->destinations[$tag];
        $this->line("  Published  ->  {$dest}");

        if (! $this->option('force')) {
            $this->newLine();
            $this->line('  <fg=gray>Tip: use <fg=white>--force</> to overwrite existing files.</fg=gray>');
        }

        $this->newLine();
        $this->info('  Done.');
        $this->newLine();

        return self::SUCCESS;
    }
}