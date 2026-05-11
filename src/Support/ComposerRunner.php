<?php

namespace RahatulRabbi\TalkBridge\Support;

/**
 * ComposerRunner
 *
 * Executes `composer require` and `composer remove` inside the host application
 * so users never need to run any Composer command manually during install/uninstall.
 */
class ComposerRunner
{
    protected string $workingDir;

    public function __construct(?string $workingDir = null)
    {
        $this->workingDir = $workingDir ?? base_path();
    }

    /**
     * Install a Composer package.
     *
     * @return array{0: bool, 1: string}  [success, output]
     */
    public function require(string $package, bool $dev = false): array
    {
        $flag = $dev ? ' --dev' : '';
        return $this->run("composer require {$package}{$flag} --no-interaction --prefer-dist 2>&1");
    }

    /**
     * Remove a Composer package.
     *
     * @return array{0: bool, 1: string}  [success, output]
     */
    public function remove(string $package): array
    {
        return $this->run("composer remove {$package} --no-interaction 2>&1");
    }

    /**
     * Rebuild the Composer autoload map.
     *
     * @return array{0: bool, 1: string}
     */
    public function dumpAutoload(): array
    {
        return $this->run('composer dump-autoload --optimize 2>&1');
    }

    /**
     * Check whether a package is installed by testing for a known class.
     */
    public function isInstalled(string $vendorClass): bool
    {
        return class_exists($vendorClass);
    }

    /**
     * Execute an arbitrary shell command in the host application root.
     *
     * @return array{0: bool, 1: string}  [success, output]
     */
    public function run(string $command): array
    {
        $output     = [];
        $returnCode = 0;

        $prevDir = getcwd();
        chdir($this->workingDir);

        exec($command, $output, $returnCode);

        chdir($prevDir);

        return [$returnCode === 0, implode("\n", $output)];
    }
}
