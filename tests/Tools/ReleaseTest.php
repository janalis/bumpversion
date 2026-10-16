<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\Tools;

use Janalis\Bumpversion\Tests\WorkspaceTestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

final class ReleaseTest extends WorkspaceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents($this->directory.'/VERSION', "0.0.0\n");
        file_put_contents($this->directory.'/CHANGELOG.md', "# Changelog\n\n## [Unreleased]\n\n### Added\n\n- A curated feature.\n\n[Unreleased]: https://example.invalid/compare/initial...HEAD\n");
        $this->configuration(['VERSION'], 'CHANGELOG.md', '0.0.0');
        rename($this->directory.'/bumpversion.yaml', $this->directory.'/bumpversion.yaml.dist');
        mkdir($this->directory.'/tools');
        mkdir($this->directory.'/bin');
        mkdir($this->directory.'/vendor');
        file_put_contents($this->directory.'/.gitignore', "/vendor/\n");
        file_put_contents($this->directory.'/vendor/autoload.php', '<?php require '.var_export($this->project.'/vendor/autoload.php', true).';');
        foreach (['tools/prepare-release.php', 'tools/release-notes.php', 'bin/bumpversion', 'bin/bumpversion.php'] as $file) {
            copy($this->project.'/'.$file, $this->directory.'/'.$file);
        }
        $this->repository();
    }

    private function tool(string $name, string $argument): Process
    {
        $process = new Process([PHP_BINARY, $this->directory.'/tools/'.$name.'.php', $argument], $this->directory);
        $process->run();

        return $process;
    }

    public function testPreparationUsesThePackageAndGeneratesVersionSpecificNotes(): void
    {
        $process = $this->tool('prepare-release', '0.1.0');
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString("version=0.1.0\ntag=v0.1.0\nprerelease=false", $process->getOutput());
        self::assertSame("0.1.0\n", file_get_contents($this->directory.'/VERSION'));
        self::assertSame('0.1.0', Yaml::parseFile($this->directory.'/bumpversion.yaml.dist')['bumpversion']['version']);
        $notes = $this->tool('release-notes', 'v0.1.0');
        self::assertSame(0, $notes->getExitCode(), $notes->getErrorOutput());
        self::assertStringContainsString('A curated feature.', $notes->getOutput());
        self::assertStringNotContainsString('## [Unreleased]', $notes->getOutput());
        self::assertSame('1.0.0', trim($this->git(['tag'])));
        self::assertSame('Initial version', trim($this->git(['log', '-1', '--format=%s'])));
    }

    public function testDirtyPreparationAndExistingTagsAreRejected(): void
    {
        file_put_contents($this->directory.'/untracked', 'pending work');
        self::assertNotSame(0, $this->tool('prepare-release', '0.1.0')->getExitCode());
        unlink($this->directory.'/untracked');
        $this->git(['tag', 'v0.1.0']);
        self::assertNotSame(0, $this->tool('prepare-release', '0.1.0')->getExitCode());
        self::assertSame("0.0.0\n", file_get_contents($this->directory.'/VERSION'));
    }

    public function testNamedBumpsAndPrereleaseMetadata(): void
    {
        $process = $this->tool('prepare-release', 'minor');
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('version=0.1.0', $process->getOutput());
    }

    public function testNotesRejectMismatchedTagAndConfiguration(): void
    {
        self::assertNotSame(0, $this->tool('release-notes', 'v0.1.0')->getExitCode());
        self::assertSame(0, $this->tool('prepare-release', '0.1.0')->getExitCode());
        file_put_contents($this->directory.'/VERSION', "0.2.0\n");
        self::assertNotSame(0, $this->tool('release-notes', 'v0.1.0')->getExitCode());
    }
}
