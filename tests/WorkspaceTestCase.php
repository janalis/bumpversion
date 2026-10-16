<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

abstract class WorkspaceTestCase extends TestCase
{
    protected string $directory;
    protected string $project;

    protected function setUp(): void
    {
        $this->project = dirname(__DIR__);
        $this->directory = sys_get_temp_dir().'/bumpversion-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        file_put_contents($this->directory.'/VERSION', "1.0.0\n");
        $this->configuration();
    }

    protected function configuration(array $paths = ['VERSION'], ?string $changelog = null, string $version = '1.0.0'): void
    {
        file_put_contents($this->directory.'/bumpversion.yaml', Yaml::dump(['bumpversion' => ['version' => $version, 'paths' => $paths, 'changelog' => $changelog]], 3));
    }

    protected function cli(array $arguments, ?string $directory = null, ?string $binary = null): Process
    {
        $process = new Process([PHP_BINARY, $binary ?? $this->project.'/bin/bumpversion', ...$arguments], $directory ?? $this->directory, ['GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null']);
        $process->run();

        return $process;
    }

    protected function git(array $arguments): string
    {
        return (new Process(['git', ...$arguments], $this->directory, ['GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null']))->mustRun()->getOutput();
    }

    protected function repository(): void
    {
        $this->git(['init', '--quiet']);
        $this->git(['config', 'user.name', 'Release Test']);
        $this->git(['config', 'user.email', 'release@example.invalid']);
        $this->git(['config', 'commit.gpgsign', 'false']);
        $this->git(['add', '.']);
        $this->git(['commit', '-qm', 'Initial version']);
        $this->git(['tag', '1.0.0']);
    }

    protected function tearDown(): void
    {
        self::remove($this->directory);
    }

    protected static function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (new \FilesystemIterator($path) as $file) {
                self::remove($file->getPathname());
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}
