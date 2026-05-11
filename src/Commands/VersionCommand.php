<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RahatulRabbi\TalkBridge\Commands\Concerns\PrintsHeader;

class VersionCommand extends Command
{
    use PrintsHeader;

    protected $signature = 'talkbridge:version
                            {--check : Check Packagist for a newer version}';

    protected $description = 'Show the installed TalkBridge version';

    protected string $packageName  = 'rahatulrabbi/talkbridge';
    protected string $packagistUrl = 'https://repo.packagist.org/p2/rahatulrabbi/talkbridge.json';
    protected string $githubUrl    = 'https://github.com/learnwithfair/talkbridge';

    public function handle(): int
    {
        $installed = $this->getInstalledVersion();

        if ($this->option('check')) {
            $this->printHeader('Version Info');
            $this->line("  <fg=white>Installed :</> <info>{$installed}</info>");
            $this->line("  <fg=white>Package   :</> {$this->packageName}");
            $this->newLine();
            $this->checkForUpdates($installed);
        } else {
            $this->printHeader();
            $this->line('  <fg=gray>Run <fg=white>php artisan talkbridge:version --check</> to check for updates.</>');
            $this->newLine();
        }

        return self::SUCCESS;
    }

    // =========================================================================
    // Version detection
    // =========================================================================

    protected function getInstalledVersion(): string
    {
        $lockFile = base_path('composer.lock');

        if (File::exists($lockFile)) {
            $lock     = json_decode(File::get($lockFile), true);
            $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

            foreach ($packages as $package) {
                if ($package['name'] === $this->packageName) {
                    return $package['version'] ?? 'unknown';
                }
            }
        }

        $vendorJson = base_path("vendor/{$this->packageName}/composer.json");

        if (File::exists($vendorJson)) {
            $data = json_decode(File::get($vendorJson), true);
            return $data['version'] ?? 'unknown';
        }

        return 'unknown';
    }

    // =========================================================================
    // Update check  (--check only)
    // =========================================================================

    protected function checkForUpdates(string $installed): void
    {
        $this->line('  <fg=gray>Checking Packagist for latest version...</>');

        try {
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $json    = @file_get_contents($this->packagistUrl, false, $context);

            if ($json === false) {
                $this->warn('  Could not reach Packagist. Check your internet connection.');
                $this->newLine();
                return;
            }

            $data     = json_decode($json, true);
            $packages = $data['packages'][$this->packageName] ?? [];

            if (empty($packages)) {
                $this->warn('  Package not found on Packagist or no versions available.');
                $this->newLine();
                return;
            }

            $stable = collect($packages)
                ->filter(fn($v) => ! str_contains($v['version'] ?? '', 'dev'))
                ->all();

            usort($stable, fn($a, $b) => version_compare(
                ltrim($b['version'] ?? '0.0.0', 'v'),
                ltrim($a['version'] ?? '0.0.0', 'v')
            ));

            $latest        = $stable[0] ?? null;
            $latestVersion = $latest['version'] ?? 'unknown';

            $this->newLine();

            if ($latestVersion === 'unknown') {
                $this->warn('  Could not determine latest version.');
            } elseif (ltrim($installed, 'v') === ltrim($latestVersion, 'v')) {
                $this->info("  ✔ You are on the latest version ({$installed}).");
            } else {
                $this->warn("  ↑ Update available: <comment>{$installed}</comment> → <info>{$latestVersion}</info>");
                $this->newLine();
                $this->line('  Update with:');
                $this->line("    <comment>php artisan talkbridge:update</comment>");
                $this->line("    <comment>php artisan talkbridge:update --to={$latestVersion}</comment>");
            }

            $this->newLine();

        } catch (\Throwable $e) {
            $this->warn('  Could not check for updates: ' . $e->getMessage());
            $this->newLine();
        }
    }
}