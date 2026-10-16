<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Release;

final class EditPlan
{
    /** @var array<string, array{before: ?string, after: string, mode: int}> */
    private array $edits = [];

    public function add(string $path, string $contents): void
    {
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new \RuntimeException('Cannot update a symlink or non-regular file: '.$path);
        }
        $before = is_file($path) ? file_get_contents($path) : null;
        if (false === $before || !is_writable(dirname($path)) || (null !== $before && !is_writable($path))) {
            throw new \RuntimeException('Cannot read or write file: '.$path);
        }
        if ($before !== $contents) {
            $this->edits[$path] = ['before' => $before, 'after' => $contents, 'mode' => null !== $before ? ((int) fileperms($path) & 0777) : (0666 & ~umask())];
        }
    }

    /** @return list<string> */
    public function files(): array
    {
        return array_keys($this->edits);
    }

    public function apply(): void
    {
        $written = [];
        try {
            foreach ($this->edits as $path => $edit) {
                $actual = is_file($path) ? file_get_contents($path) : null;
                if (is_link($path) || $actual !== $edit['before']) {
                    throw new \RuntimeException('File changed after planning: '.$path);
                }
                self::write($path, $edit['after'], $edit['mode']);
                $written[] = $path;
            }
        } catch (\Throwable $exception) {
            $failures = [];
            foreach (array_reverse($written) as $path) {
                try {
                    $edit = $this->edits[$path];
                    if (null === $edit['before']) {
                        if (!unlink($path)) {
                            throw new \RuntimeException('Cannot remove '.$path);
                        }
                    } else {
                        self::write($path, $edit['before'], $edit['mode']);
                    }
                } catch (\Throwable) {
                    $failures[] = $path;
                }
            }
            throw new \RuntimeException('Update failed: '.$exception->getMessage().([] !== $failures ? ' Could not restore: '.implode(', ', $failures) : ' Earlier writes restored.'), 0, $exception);
        }
    }

    private static function write(string $path, string $contents, int $mode): void
    {
        $temporary = tempnam(dirname($path), '.bumpversion-');
        if (false === $temporary) {
            throw new \RuntimeException('Cannot create temporary file.');
        }
        try {
            if (file_put_contents($temporary, $contents) !== strlen($contents) || !chmod($temporary, $mode) || !rename($temporary, $path)) {
                throw new \RuntimeException('Cannot write file: '.$path);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
