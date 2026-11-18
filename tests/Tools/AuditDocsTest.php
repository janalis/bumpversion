<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\Tools;

use Janalis\Bumpversion\Tests\WorkspaceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

final class AuditDocsTest extends WorkspaceTestCase
{
    private const ACCEPTED = ['name' => 'picomatch', 'url' => 'https://github.com/advisories/GHSA-fc5q-39xc-vjpf'];

    public function testAcceptsTheListedAdvisoryAndPackagesDependingOnIt(): void
    {
        $process = $this->audit((string) json_encode(['vulnerabilities' => [
            'picomatch' => ['via' => [self::ACCEPTED]],
            'vite' => ['via' => ['picomatch']],
        ]]));

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('GHSA-fc5q-39xc-vjpf', $process->getOutput());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedReports(): iterable
    {
        $other = ['name' => 'vite', 'url' => 'https://github.com/advisories/GHSA-aaaa-bbbb-cccc'];

        yield 'another advisory' => [(string) json_encode(['vulnerabilities' => [
            'picomatch' => ['via' => [self::ACCEPTED]],
            'vite' => ['via' => [$other, 'picomatch']],
        ]]), 'GHSA-aaaa-bbbb-cccc'];
        yield 'listed advisory on another package' => [(string) json_encode(['vulnerabilities' => [
            'picomatch' => ['via' => [self::ACCEPTED]],
            'other' => ['via' => [['name' => 'other', 'url' => self::ACCEPTED['url']]]],
        ]]), 'other: GHSA-fc5q-39xc-vjpf'];
        yield 'dependency missing from the report' => [(string) json_encode(['vulnerabilities' => [
            'picomatch' => ['via' => [self::ACCEPTED]],
            'vite' => ['via' => ['missing']],
        ]]), 'vite: unknown advisory'];
        yield 'package without details' => [(string) json_encode(['vulnerabilities' => [
            'picomatch' => ['via' => [self::ACCEPTED]],
            'vite' => ['via' => []],
        ]]), 'vite: no advisory details'];
        yield 'listed advisory no longer reported' => [(string) json_encode(['vulnerabilities' => []]), 'no longer reported'];
        yield 'npm error' => [(string) json_encode(['error' => ['code' => 'ENOAUDIT'], 'vulnerabilities' => []]), 'usable report'];
        yield 'invalid report' => ['not json', 'usable report'];
    }

    #[DataProvider('rejectedReports')]
    public function testRejectsAnythingElse(string $report, string $message): void
    {
        $process = $this->audit($report);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString($message, $process->getErrorOutput());
    }

    private function audit(string $report): Process
    {
        file_put_contents($this->directory.'/audit.json', $report);
        $process = new Process([\PHP_BINARY, dirname(__DIR__, 2).'/tools/audit-docs.php', '--report='.$this->directory.'/audit.json']);
        $process->run();

        return $process;
    }
}
