<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\Command;

use Janalis\Bumpversion\Command\InitCommand;
use Janalis\Bumpversion\Tests\WorkspaceTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

final class CommandsTest extends WorkspaceTestCase
{
    public function testDefaultBumpAndBoundaryMatching(): void
    {
        file_put_contents($this->directory.'/VERSION', "1.0.0 11.0.0 1.0.01 1.0.0-alpha 1.0.0+build 1.0.0.9\nVersion: 1.0.0.\n");
        chmod($this->directory.'/VERSION', 0755);
        $process = $this->cli(['bump']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("1.0.1 11.0.0 1.0.01 1.0.0-alpha 1.0.0+build 1.0.0.9\nVersion: 1.0.1.\n", file_get_contents($this->directory.'/VERSION'));
        self::assertSame(0755, fileperms($this->directory.'/VERSION') & 0777);
        self::assertSame('1.0.1', Yaml::parseFile($this->directory.'/bumpversion.yaml')['bumpversion']['version']);
    }

    public function testDryRunLeavesAllBytesUnchanged(): void
    {
        $before = file_get_contents($this->directory.'/bumpversion.yaml');
        $process = $this->cli(['bump', '--dry-run']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('1.0.0 → 1.0.1', $process->getOutput());
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
        self::assertSame($before, file_get_contents($this->directory.'/bumpversion.yaml'));
    }

    public function testConfigurationRelativePathsAndDistFallback(): void
    {
        rename($this->directory.'/bumpversion.yaml', $this->directory.'/bumpversion.yaml.dist');
        $process = $this->cli(['bump', '-c', $this->directory.'/bumpversion.yaml', '--new-version=2.5.0-rc.1+build.4'], sys_get_temp_dir());
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("2.5.0-rc.1+build.4\n", file_get_contents($this->directory.'/VERSION'));
        self::assertFileDoesNotExist($this->directory.'/bumpversion.yaml');
    }

    public function testDirectoriesAreExactAndOverlapsDeduplicated(): void
    {
        mkdir($this->directory.'/src');
        mkdir($this->directory.'/src-extra');
        mkdir($this->directory.'/src/vendor');
        file_put_contents($this->directory.'/src/version', '1.0.0');
        file_put_contents($this->directory.'/src-extra/version', '1.0.0');
        file_put_contents($this->directory.'/src/vendor/version', '1.0.0');
        $this->configuration(['src', 'src/version']);
        $process = $this->cli(['bump']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame('1.0.1', file_get_contents($this->directory.'/src/version'));
        self::assertSame('1.0.0', file_get_contents($this->directory.'/src-extra/version'));
        self::assertSame('1.0.0', file_get_contents($this->directory.'/src/vendor/version'));
        self::assertSame(1, substr_count($process->getOutput(), 'Update src/version'));
    }

    public function testMissingMatchFailsBeforeAnyWrites(): void
    {
        file_put_contents($this->directory.'/other', 'no version');
        $this->configuration(['VERSION', 'other']);
        self::assertNotSame(0, $this->cli(['bump'])->getExitCode());
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
    }

    public function testMissingPathAndEscapingPathsAreRejected(): void
    {
        foreach (['missing', '..', 'vendor/file'] as $path) {
            $this->configuration([$path]);
            self::assertNotSame(0, $this->cli(['bump'])->getExitCode());
        }
    }

    public function testSymlinkIsRejectedAndDirectorySymlinksAreSkipped(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Symlink creation requires elevated permissions on Windows.');
        }
        symlink($this->directory.'/VERSION', $this->directory.'/linked');
        $this->configuration(['linked']);
        self::assertNotSame(0, $this->cli(['bump'])->getExitCode());
        $this->configuration(['.']);
        self::assertSame(0, $this->cli(['bump'])->getExitCode());
        self::assertTrue(is_link($this->directory.'/linked'));
    }

    public function testInvalidConfigurationAndConflictingOptions(): void
    {
        self::assertNotSame(0, $this->cli(['bump', '--new-version=2.0.0', '--type=major'])->getExitCode());
        self::assertNotSame(0, $this->cli(['bump', '--new-version=1.0.0'])->getExitCode());
        foreach (['bumpversion: [', 'bumpversion: null', "bumpversion:\n  version: 1.0.0\n  paths: bad", "bumpversion:\n  version: 1.0.0\n  paths: []", "bumpversion:\n  version: 1.0.0\n  paths: [42]"] as $invalid) {
            file_put_contents($this->directory.'/bumpversion.yaml', $invalid);
            self::assertNotSame(0, $this->cli(['bump'])->getExitCode());
        }
    }

    public function testNoninteractiveInitAndOverwriteProtection(): void
    {
        unlink($this->directory.'/bumpversion.yaml');
        self::assertNotSame(0, $this->cli(['init', '--no-interaction'])->getExitCode());
        $process = $this->cli(['init', '--no-interaction', '--current-version=1.0.0', '--path=VERSION']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertNotSame(0, $this->cli(['init', '--no-interaction', '--current-version=2.0.0', '--path=VERSION'])->getExitCode());
        self::assertSame('1.0.0', Yaml::parseFile($this->directory.'/bumpversion.yaml')['bumpversion']['version']);
    }

    public function testInteractiveInit(): void
    {
        unlink($this->directory.'/bumpversion.yaml');
        $tester = new CommandTester(new InitCommand());
        $tester->setInputs(['1.0.0', '', 'VERSION', '']);
        $tester->execute(['--configuration' => $this->directory.'/bumpversion.yaml']);
        $tester->assertCommandIsSuccessful();
        self::assertSame(['VERSION'], Yaml::parseFile($this->directory.'/bumpversion.yaml')['bumpversion']['paths']);
    }

    public function testMissingConfigurationFailsWithoutPrompting(): void
    {
        unlink($this->directory.'/bumpversion.yaml');
        self::assertNotSame(0, $this->cli(['bump', '--no-interaction'])->getExitCode());
        self::assertFileDoesNotExist($this->directory.'/bumpversion.yaml');
    }

    public function testAdditionalConfigurationKeysArePreserved(): void
    {
        file_put_contents($this->directory.'/bumpversion.yaml', "other: keep\nbumpversion:\n  version: 1.0.0\n  paths: [VERSION]\n  extension: keep\n");
        self::assertSame(0, $this->cli(['bump'])->getExitCode());
        $data = Yaml::parseFile($this->directory.'/bumpversion.yaml');
        self::assertSame('keep', $data['other']);
        self::assertSame('keep', $data['bumpversion']['extension']);
    }

    public function testInvalidChangelogFailsBeforeWriting(): void
    {
        $this->configuration(['VERSION'], 'missing.md');
        self::assertNotSame(0, $this->cli(['bump'])->getExitCode());
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
    }

    public function testBinaryFilesAreSkippedInDirectoriesAndRejectedExplicitly(): void
    {
        file_put_contents($this->directory.'/binary', "1.0.0\0");
        $this->configuration(['binary']);
        self::assertNotSame(0, $this->cli(['bump'])->getExitCode());
        $this->configuration(['.']);
        self::assertSame(0, $this->cli(['bump'])->getExitCode());
        self::assertSame("1.0.0\0", file_get_contents($this->directory.'/binary'));
    }
}
