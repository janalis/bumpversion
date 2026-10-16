<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\VersionControlSystem;

use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Process\Process;

final class GitVersionControlSystem
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): string
    {
        $process = new Process(['git', '--literal-pathspecs', ...$arguments], $this->directory);
        $process->mustRun();

        return $process->getOutput();
    }

    public function preflight(string $next, bool $tag, bool $commit = false): void
    {
        $this->run(['rev-parse', '--verify', 'HEAD']);
        if ($commit) {
            $this->run(['var', 'GIT_AUTHOR_IDENT']);
            $this->run(['var', 'GIT_COMMITTER_IDENT']);
        }
        if ('' !== trim($this->run(['status', '--porcelain', '--untracked-files=all']))) {
            throw new \RuntimeException('Commit or stash all changes, including untracked files, before a Git release.');
        }
        if ($tag) {
            $this->run(['check-ref-format', 'refs/tags/'.$next]);
        }
        if ($tag && in_array($next, explode("\n", trim($this->run(['tag', '--list']))), true)) {
            throw new \RuntimeException('Release tag already exists: '.$next);
        }
    }

    /** @param list<string> $files */
    public function validateFiles(array $files): void
    {
        foreach ($files as $file) {
            $process = new Process(['git', '--no-literal-pathspecs', 'check-ignore', '--quiet', '--', $file], $this->directory);
            $process->run();
            if (0 === $process->getExitCode()) {
                throw new \RuntimeException('A planned release file is ignored by Git.');
            }
            if (1 !== $process->getExitCode()) {
                throw new \RuntimeException('Cannot check Git ignore rules: '.$process->getErrorOutput());
            }
        }
    }

    /** @return list<SemanticVersion> */
    public function versions(): array
    {
        $versions = [];
        foreach (explode("\n", trim($this->run(['tag', '--list']))) as $tag) {
            try {
                $versions[] = SemanticVersion::parse(preg_replace('/^v/', '', $tag) ?? $tag);
            } catch (\InvalidArgumentException) {
                // Repositories may contain tags unrelated to releases.
            }
        }
        usort($versions, static fn ($a, $b) => $a->compare($b));

        return $versions;
    }

    /** @param list<string> $files */
    public function release(array $files, string $current, string $next, bool $commit, bool $tag, ?string $tagName = null): void
    {
        $committed = false;
        try {
            if ($commit) {
                $this->run(['add', '--', ...$files]);
                $this->run(['commit', '-m', "Bump version: $current → $next", '--', ...$files]);
                $committed = true;
            }
            if ($tag) {
                $this->run(['tag', '--', $tagName ?? $next]);
            }
        } catch (\Throwable $exception) {
            throw new \RuntimeException(($committed ? 'Release commit created, but tagging failed. ' : 'Files updated, but Git release failed. Inspect the working tree and index. ').$exception->getMessage(), 0, $exception);
        }
    }
}
