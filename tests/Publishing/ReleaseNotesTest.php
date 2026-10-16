<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\Publishing;

use Janalis\Bumpversion\Changelog\KeepAChangelog;
use Janalis\Bumpversion\Configuration\YamlConfiguration;
use Janalis\Bumpversion\Tests\WorkspaceTestCase;
use Janalis\Bumpversion\Version\SemanticVersion;

final class ReleaseNotesTest extends WorkspaceTestCase
{
    public function testHistoricalNotesPreserveFencesAndReferencesWithCrLf(): void
    {
        $text = "# Changelog\n\n## [Unreleased]\n\n## [1.1.0] - 2026-10-16\n\n### Added\n\n- New feature.\n\n## [1.0.0] - 2026-10-15 [YANKED]\n\n### Added\n\n- See [guide].\n\n```md\n## [9.0.0] - 2026-01-01\n[hidden]: example\n```\n\n[guide]: https://example.invalid/guide\n";
        $notes = (new KeepAChangelog())->notes(str_replace("\n", "\r\n", $text), SemanticVersion::parse('1.0.0'));
        self::assertStringStartsWith('# 1.0.0 - 2026-10-15 [YANKED]', $notes);
        self::assertStringContainsString("```md\n## [9.0.0] - 2026-01-01\n[hidden]: example\n```", $notes);
        self::assertStringContainsString('[guide]: https://example.invalid/guide', $notes);
        self::assertStringNotContainsString('New feature', $notes);
        self::assertStringNotContainsString("\r", $notes);
    }

    public function testInvalidDateAndMissingNotesAreRejected(): void
    {
        $this->expectExceptionMessage('release date is invalid');
        (new KeepAChangelog())->notes("## [1.0.0] - 2026-02-30\n\n- Feature\n", SemanticVersion::parse('1.0.0'));
    }

    public function testPublishingSettingsRoundTripAndRejectCredentialKeys(): void
    {
        $config = new YamlConfiguration(['VERSION'], SemanticVersion::parse('1.0.0'), publishing: ['github_repository' => 'example/package', 'http_client' => 'guzzle']);
        file_put_contents($this->directory.'/bumpversion.yaml', $config->contents());
        self::assertSame($config->publishing, YamlConfiguration::load($this->directory.'/bumpversion.yaml')->publishing);
        file_put_contents($this->directory.'/bumpversion.yaml', "bumpversion:\n  version: 1.0.0\n  publishing:\n    token: synthetic-secret\n");
        $this->expectExceptionMessage('Store credentials in environment');
        YamlConfiguration::load($this->directory.'/bumpversion.yaml');
    }

    public function testCliPublishingDryRunsNeedNoGitOrCredentials(): void
    {
        file_put_contents($this->directory.'/notes.md', '- Curated feature');
        file_put_contents($this->directory.'/composer.json', '{"name":"example/consumer"}');
        $sha = str_repeat('1', 40);
        $github = $this->cli(['release:github', '--repository=example/package', '--commit='.$sha, '--notes-file=notes.md', '--asset=VERSION', '--dry-run']);
        self::assertSame(0, $github->getExitCode(), $github->getErrorOutput());
        self::assertStringContainsString('example/package/releases/tag/v1.0.0', $github->getOutput());
        $packagist = $this->cli(['release:packagist', '--commit='.$sha, '--dry-run']);
        self::assertSame(0, $packagist->getExitCode(), $packagist->getErrorOutput());
        self::assertStringContainsString('example/consumer', $packagist->getOutput());
    }
}
