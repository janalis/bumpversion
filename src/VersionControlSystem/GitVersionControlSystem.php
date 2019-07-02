<?php

declare(strict_types=1);

namespace App\VersionControlSystem;

use App\Version\VersionInterface;
use Symfony\Component\Process\Process;

class GitVersionControlSystem implements VersionControlSystemInterface
{
    public function getChanges(?string $from = null, ?string $to = null): array
    {
        $output = $this->execute(['git', 'log', '--reverse', '--pretty=format:"%H %s by %an at %ad"', implode('...', [$from ?? 'HEAD', $to ?? 'HEAD'])]);

        return array_map(static function (string $log): ChangeInterface {
            preg_match('/^([\w]+) (.*) by (.*) at (.*)$/', trim($log), $matches);
            [, $identifier, $subject, $author, $date] = $matches;
            $date = new \DateTime($date);

            return new Change($identifier, $subject, $author, $date);
        }, preg_split("/\r\n|\n|\r/", $output));
    }

    public function getIdentifier(): string
    {
        return 'git';
    }

    public function getProcess(array $command): Process
    {
        return new Process($command);
    }

    public function getVersions(string $versioning): array
    {
        $output = $this->execute(['git', 'tag', '-l']);

        $versions = array_map(static function (string $tag) use ($versioning): VersionInterface {
            return call_user_func(sprintf('%s::%s', $versioning, 'parse'), $tag);
        }, preg_split("/\r\n|\n|\r/", $output));

        usort($versions, static function (VersionInterface $a, VersionInterface $b): int {
            return version_compare((string) $a, (string) $b);
        });

        return $versions;
    }

    public function isDirty(): bool
    {
        try {
            $this->execute(['git', 'diff-index', '--quiet', 'HEAD']);
        } catch (\Exception $exception) {
            return true;
        }

        return false;
    }

    public function release(string $currentVersion, string $nextVersion, ?string $description = null): void
    {
        if ($this->isDirty()) {
            $this->execute(['git', 'add', '.']);
            $this->execute(['git', 'commit', '-m', sprintf('bump version %s → %s', $currentVersion, $nextVersion)]);
        }

        $this->execute(array_merge(['git', 'tag', $nextVersion], $description ? ['-m', $description] : []));
    }

    private function execute(array $command): string
    {
        $process = $this->getProcess($command);
        $process->mustRun();

        if ($code = $process->getExitCode()) {
            throw new \RuntimeException($process->getErrorOutput(), $code);
        }

        return $process->getOutput();
    }
}
