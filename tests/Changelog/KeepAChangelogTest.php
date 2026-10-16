<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\Changelog;

use Janalis\Bumpversion\Changelog\KeepAChangelog;
use Janalis\Bumpversion\Tests\WorkspaceTestCase;
use Janalis\Bumpversion\Version\SemanticVersion;
use PHPUnit\Framework\Attributes\DataProvider;

final class KeepAChangelogTest extends WorkspaceTestCase
{
    private const HISTORY = "## [1.0.0] - 2026-01-01 [YANKED]\n\n### Security\n\n- Historical entry with 1.0.0.\n";

    public function testReleaseWithoutGitPreservesCategoriesHistoryAndReferences(): void
    {
        $body = '';
        foreach (['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security'] as $category) {
            $body .= "### $category\n\n- Curated $category entry.\n\n";
        }
        $contents = "# Changelog\n\n## [Unreleased]\n\n".$body.self::HISTORY."\n[Unreleased]: https://example.invalid/project/compare/v1.0.0...HEAD\n[1.0.0]: https://example.invalid/project/releases/tag/v1.0.0\n[issue]: https://example.invalid/issues/1\n";
        file_put_contents($this->directory.'/CHANGELOG.md', $contents);
        $this->configuration(['.'], 'CHANGELOG.md');
        $process = $this->cli(['bump', '--type=minor', '--date=2026-02-01']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $actual = file_get_contents($this->directory.'/CHANGELOG.md');
        self::assertStringContainsString("## [Unreleased]\n\n## [1.1.0] - 2026-02-01\n\n".$body, $actual);
        self::assertStringContainsString(self::HISTORY, $actual);
        self::assertStringContainsString('[Unreleased]: https://example.invalid/project/compare/v1.1.0...HEAD', $actual);
        self::assertStringContainsString('[1.1.0]: https://example.invalid/project/compare/v1.0.0...v1.1.0', $actual);
        self::assertStringContainsString('[issue]: https://example.invalid/issues/1', $actual);
        self::assertSame("1.1.0\n", file_get_contents($this->directory.'/VERSION'));
    }

    public function testDryRunDoesNotReleaseNotes(): void
    {
        $contents = "# Changelog\n\n## [Unreleased]\n\n### Fixed\n\n- A fix.\n";
        file_put_contents($this->directory.'/CHANGELOG.md', $contents);
        $this->configuration(['VERSION'], 'CHANGELOG.md');
        $process = $this->cli(['bump', '--dry-run', '--date=2026-02-01']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame($contents, file_get_contents($this->directory.'/CHANGELOG.md'));
    }

    public function testLineEndingsAndUnlinkedChangelog(): void
    {
        $contents = "# Changelog\r\n\r\n## Unreleased\r\n\r\n### Fixed\r\n\r\n- A fix.\r\n\r\n".str_replace("\n", "\r\n", self::HISTORY);
        $result = (new KeepAChangelog())->release($contents, SemanticVersion::parse('1.0.1-rc.1+build.2'), '2026-02-01');
        self::assertStringContainsString("## [1.0.1-rc.1+build.2] - 2026-02-01\r\n", $result);
        self::assertStringContainsString(str_replace("\n", "\r\n", self::HISTORY), $result);
        self::assertStringNotContainsString("\n", str_replace("\r\n", '', $result));
        self::assertStringNotContainsString('https://', $result);
    }

    public function testCodeFencesDoNotBecomeChangelogSections(): void
    {
        $body = "### Added\n\n- Markdown examples:\n\n```markdown\n## [Unreleased]\n### Custom\n[Unreleased]: https://example.invalid/compare/v0...HEAD\n```\n";
        $result = (new KeepAChangelog())->release("# Changelog\n\n## [Unreleased]\n\n".$body."\n".self::HISTORY, SemanticVersion::parse('1.0.1'), '2026-02-01');
        self::assertStringContainsString($body, $result);
        self::assertStringContainsString(self::HISTORY, $result);
    }

    public static function invalidChangelogs(): iterable
    {
        yield ["# Changelog\n", '2026-02-01'];
        yield ["## [Unreleased]\n\n## [Unreleased]\n", '2026-02-01'];
        yield ["## [Unreleased]\n\n### Custom\n\n- Entry.\n", '2026-02-01'];
        yield ["## [Unreleased]\n\n### Fixed\n\n- Entry.\n\n### Fixed\n\n- Duplicate.\n", '2026-02-01'];
        yield ["## [Unreleased]\n\n- Uncategorized.\n", '2026-02-01'];
        yield ["## [Unreleased]\n\n## [1.0.1] - 2026-01-01\n", '2026-02-01'];
        yield ["## [1.0.0] - 2026-01-01\n\n## [Unreleased]\n", '2026-02-01'];
        yield ["## [Unreleased]\n", '2026-02-30'];
        yield ["## [Unreleased]\n", '02/01/2026'];
        yield ["## [Unreleased]\n\n[1.0.1]: https://example.invalid/old\n", '2026-02-01'];
    }

    #[DataProvider('invalidChangelogs')]
    public function testMalformedChangelogFailsBeforeAnyWrites(string $contents, string $date): void
    {
        file_put_contents($this->directory.'/CHANGELOG.md', $contents);
        $this->configuration(['VERSION'], 'CHANGELOG.md');
        $process = $this->cli(['bump', '--date='.$date]);
        self::assertNotSame(0, $process->getExitCode());
        self::assertSame($contents, file_get_contents($this->directory.'/CHANGELOG.md'));
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
    }

    public function testTargetsMustIncreaseSemverPrecedence(): void
    {
        foreach (['0.9.9', '1.0.0-alpha', '1.0.0+another-build'] as $target) {
            self::assertNotSame(0, $this->cli(['bump', '--new-version='.$target])->getExitCode());
        }
        $process = $this->cli(['bump', '--pre-release=alpha']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("1.0.1-alpha.1\n", file_get_contents($this->directory.'/VERSION'));
    }

    public function testEmptyCategoriesAreOmittedAndReferenceTitlesSurvive(): void
    {
        $contents = "# Changelog\n\n## [Unreleased]\n\n### Added\n\n### Fixed\n\n- A fix.\n\n### Security\n\n[Unreleased]: <https://example.invalid/compare/v1.0.0...HEAD> \"Upcoming changes\"\n";
        self::assertSame('v', KeepAChangelog::tagPrefix($contents, '1.0.0'));
        $result = (new KeepAChangelog())->release($contents, SemanticVersion::parse('1.0.1'), '2026-02-01', 'v');
        self::assertStringNotContainsString('### Added', $result);
        self::assertStringNotContainsString('### Security', $result);
        self::assertStringContainsString("### Fixed\n\n- A fix.", $result);
        self::assertStringContainsString('[Unreleased]: <https://example.invalid/compare/v1.0.1...HEAD> "Upcoming changes"', $result);
        self::assertStringContainsString('[1.0.1]: <https://example.invalid/compare/v1.0.0...v1.0.1>', $result);
    }
}
