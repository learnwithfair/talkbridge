<?php

namespace RahatulRabbi\TalkBridge\Commands\Concerns;

trait PrintsHeader
{
    protected function printHeader(string $subtitle = ''): void
    {
        $version = class_exists(\Composer\InstalledVersions::class)
            ? 'v' . (\Composer\InstalledVersions::getPrettyVersion('rahatulrabbi/talkbridge') ?? '1.x')
            : 'v1.x';

        $color = $this->headerColor();

        $this->newLine();
        $this->line("<fg={$color}>
  ████████╗ █████╗ ██╗     ██╗  ██╗██████╗ ██████╗ ██╗██████╗  ██████╗ ███████╗
  ╚══██╔══╝██╔══██╗██║     ██║ ██╔╝██╔══██╗██╔══██╗██║██╔══██╗██╔════╝ ██╔════╝
     ██║   ███████║██║     █████╔╝ ██████╔╝██████╔╝██║██║  ██║██║  ███╗█████╗
     ██║   ██╔══██║██║     ██╔═██╗ ██╔══██╗██╔══██╗██║██║  ██║██║   ██║██╔══╝
     ██║   ██║  ██║███████╗██║  ██╗██████╔╝██║  ██║██║██████╔╝╚██████╔╝███████╗
     ╚═╝   ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝╚═════╝ ╚═╝  ╚═╝╚═╝╚═════╝  ╚═════╝ ╚══════╝</>");
        $this->newLine();

        $suffix = $subtitle ? "  — {$subtitle}" : '';
        $this->line("  <fg=cyan>Real-time Chat Package for Laravel</>  <fg=yellow>{$version}{$suffix}</>");
         $this->line('  <fg=yellow>by MD. RAHATUL RABBI (Software Engineer)</>');       
        $this->newLine();
    }

    /**
     * Override in each command to change the ASCII art color.
     * install/publish/update = blue, uninstall = red, version = blue
     */
    protected function headerColor(): string
    {
        return 'blue';
    }
}