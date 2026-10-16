<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\VersionControlSystem;

use Janalis\Bumpversion\Tests\WorkspaceTestCase;
use Janalis\Bumpversion\VersionControlSystem\GitVersionControlSystem;
use PHPUnit\Framework\Attributes\DataProvider;

final class GitTest extends WorkspaceTestCase
{
    public static function modes(): iterable
    {
        yield [['--commit'], true, false];
        yield [['--tag'], false, true];
        yield [['--commit', '--tag'], true, true];
        yield [['--version-control-system=git'], true, true];
    }

    #[DataProvider('modes')]
    public function testReleaseModes(array $flags, bool $commit, bool $tag): void
    {
        $this->repository();
        $head = trim($this->git(['rev-parse', 'HEAD']));
        $process = $this->cli(['bump', ...$flags]);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame($commit, $head !== trim($this->git(['rev-parse', 'HEAD'])));
        self::assertSame($tag, in_array('1.0.1', explode("\n", trim($this->git(['tag']))), true));
        if ($tag) {
            self::assertSame(trim($this->git(['rev-parse', 'HEAD'])), trim($this->git(['rev-parse', '1.0.1'])));
        }
        self::assertSame($commit, '' === trim($this->git(['status', '--porcelain'])));
    }

    public function testDirtyAndDuplicateTagPreflight(): void
    {
        $this->repository();
        file_put_contents($this->directory.'/private-untracked', 'unrelated');
        self::assertNotSame(0, $this->cli(['bump', '--commit'])->getExitCode());
        unlink($this->directory.'/private-untracked');
        file_put_contents($this->directory.'/VERSION', "1.0.0 changed\n");
        self::assertNotSame(0, $this->cli(['bump', '--tag'])->getExitCode());
        $this->git(['checkout', '--', 'VERSION']);
        $this->git(['tag', '1.0.1']);
        self::assertNotSame(0, $this->cli(['bump', '--tag'])->getExitCode());
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
    }

    public function testDryRunAndScopedStaging(): void
    {
        $this->repository();
        // Ignored unrelated files must never be staged.
        file_put_contents($this->directory.'/.git/info/exclude', "ignored\n");
        file_put_contents($this->directory.'/ignored', 'private data');
        $head = $this->git(['rev-parse', 'HEAD']);
        self::assertSame(0, $this->cli(['bump', '--commit', '--tag', '--dry-run'])->getExitCode());
        self::assertSame($head, $this->git(['rev-parse', 'HEAD']));
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
        self::assertSame(0, $this->cli(['bump', '--commit', '--tag'])->getExitCode());
        self::assertSame(['VERSION', 'bumpversion.yaml'], explode("\n", trim($this->git(['diff-tree', '--no-commit-id', '--name-only', '-r', 'HEAD']))));
    }

    public function testCuratedChangelogAndPrefixedTags(): void
    {
        file_put_contents($this->directory.'/CHANGELOG.md', "# Changelog\n\n## [Unreleased]\n\n### Added\n\n- Curated release note.\n\n[unreleased]: https://example.invalid/project/compare/v1.0.0...HEAD\n");
        $this->configuration(['VERSION'], 'CHANGELOG.md');
        $this->repository();
        $process = $this->cli(['bump', '--commit', '--tag', '--date=2026-10-16']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $contents = file_get_contents($this->directory.'/CHANGELOG.md');
        self::assertStringContainsString('## [1.0.1] - 2026-10-16', $contents);
        self::assertStringContainsString('- Curated release note.', $contents);
        self::assertStringNotContainsString('Initial version', $contents);
        self::assertStringNotContainsString('Release Test', $contents);
        self::assertStringContainsString('compare/v1.0.1...HEAD', $contents);
        self::assertSame('v1.0.1', trim($this->git(['tag', '--list', 'v1.0.1'])));
        $adapter = new GitVersionControlSystem($this->directory);
        $this->git(['tag', 'unrelated']);
        $this->git(['tag', 'v2.0.0']);
        self::assertSame(['1.0.0', '1.0.1', '2.0.0'], array_map(strval(...), $adapter->versions()));
    }

    public function testCommitFailureReportsUpdatedFiles(): void
    {
        $this->repository();
        file_put_contents($this->directory.'/.git/hooks/pre-commit', "#!/bin/sh\nexit 1\n");
        chmod($this->directory.'/.git/hooks/pre-commit', 0755);
        $process = $this->cli(['bump', '--commit', '--tag']);
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('Files updated', $process->getErrorOutput());
        self::assertSame("1.0.1\n", file_get_contents($this->directory.'/VERSION'));
        self::assertSame('1.0.0', trim($this->git(['tag'])));
    }

    public function testTagFailureRetainsCreatedCommit(): void
    {
        $this->repository();
        $head = $this->git(['rev-parse', 'HEAD']);
        file_put_contents($this->directory.'/.git/hooks/post-commit', "#!/bin/sh\ngit tag 1.0.1\n");
        chmod($this->directory.'/.git/hooks/post-commit', 0755);
        $process = $this->cli(['bump', '--commit', '--tag']);
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('Release commit created, but tagging failed', $process->getErrorOutput());
        self::assertNotSame($head, $this->git(['rev-parse', 'HEAD']));
        self::assertSame('', trim($this->git(['status', '--porcelain'])));
    }

    public function testIgnoredTargetFailsBeforeWriting(): void
    {
        $this->repository();
        file_put_contents($this->directory.'/.git/info/exclude', "ignored-version\n");
        file_put_contents($this->directory.'/ignored-version', '1.0.0');
        $this->configuration(['VERSION', 'ignored-version']);
        $this->git(['add', 'bumpversion.yaml']);
        $this->git(['commit', '-qm', 'Configure release']);
        $process = $this->cli(['bump', '--commit']);
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('ignored by Git', $process->getErrorOutput());
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
    }

    public function testSpecialFilenamesAreLiteralGitPathspecs(): void
    {
        file_put_contents($this->directory.'/version[1]', '1.0.0');
        file_put_contents($this->directory.'/version1', 'unrelated');
        $this->configuration(['version[1]']);
        $this->repository();
        $process = $this->cli(['bump', '--commit']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame('1.0.1', file_get_contents($this->directory.'/version[1]'));
        self::assertSame('unrelated', file_get_contents($this->directory.'/version1'));
    }

    public function testNoRepositoryOrHeadFailsWithoutWriting(): void
    {
        self::assertNotSame(0, $this->cli(['bump', '--commit'])->getExitCode());
        $this->git(['init', '--quiet']);
        self::assertNotSame(0, $this->cli(['bump', '--tag'])->getExitCode());
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
    }
}
