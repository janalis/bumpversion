<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Release;

use Janalis\Bumpversion\Changelog\KeepAChangelog;
use Janalis\Bumpversion\Configuration\YamlConfiguration;
use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Finder\Finder;

final class ReleasePlanner
{
    private const EXCLUDED = ['.git', '.hg', '.svn', 'vendor', 'node_modules', '.cache', '.phpunit.cache', 'dist'];

    /** @var list<string> */
    public array $reports = [];

    public function plan(string $filename, YamlConfiguration $config, SemanticVersion $next, ?string $date = null, string $tagPrefix = ''): EditPlan
    {
        $root = dirname($filename);
        $plan = new EditPlan();
        $seen = [];
        $changelog = null !== $config->changelog ? self::path($root, $config->changelog) : null;
        if (null !== $changelog && ($changelog === $filename || !is_file($changelog))) {
            throw new \RuntimeException('Changelog must be a separate regular file.');
        }
        $pattern = '/(?<![0-9A-Za-z_.+-])'.preg_quote((string) $config->version, '/').'(?![0-9A-Za-z_+-]|\.[0-9A-Za-z])/';
        foreach ($config->paths as $configured) {
            $path = self::path($root, $configured);
            $files = [];
            if (is_file($path)) {
                $files[] = $path;
            } else {
                $finder = (new Finder())->files()->in($path)->ignoreDotFiles(false)->ignoreVCS(true)->exclude(self::EXCLUDED)->sortByName();
                foreach ($finder as $file) {
                    if (!$file->isLink()) {
                        $files[] = $file->getPathname();
                    }
                }
            }
            $matches = 0;
            foreach ($files as $file) {
                $file = self::path($root, $file);
                if ($file === $filename || $file === $changelog) {
                    continue;
                }
                $contents = file_get_contents($file);
                if (false === $contents) {
                    throw new \RuntimeException('Cannot read configured file.');
                }
                if (str_contains($contents, "\0")) {
                    if (is_file($path)) {
                        throw new \RuntimeException('Configured file is binary: '.$configured);
                    }
                    continue;
                }
                $changed = preg_replace_callback($pattern, static fn () => (string) $next, $contents, -1, $count);
                if (null === $changed) {
                    throw new \RuntimeException('Version replacement failed.');
                }
                $matches += $count;
                if ($count > 0 && !isset($seen[$file])) {
                    $plan->add($file, $changed);
                    $seen[$file] = true;
                }
            }
            if (is_file($path) && 0 === $matches && $path !== $filename && $path !== $changelog) {
                throw new \RuntimeException('Current version not found in configured file: '.$configured);
            }
            $this->reports[] = sprintf('%s: %d match(es)', $configured, $matches);
        }
        if (null !== $changelog) {
            $contents = file_get_contents($changelog);
            if (false === $contents) {
                throw new \RuntimeException('Cannot read changelog.');
            }
            // Release curated notes without rewriting historic versions.
            $plan->add($changelog, (new KeepAChangelog())->release($contents, $next, $date ?? gmdate('Y-m-d'), $tagPrefix));
        }
        $config->version = $next;
        $plan->add($filename, $config->contents());

        return $plan;
    }

    public static function path(string $root, string $path): string
    {
        $candidate = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : $root.DIRECTORY_SEPARATOR.$path;
        // Reject symlinks in every path component, including directory ancestors.
        $check = $candidate;
        while ($check !== dirname($check)) {
            if (is_link($check)) {
                throw new \RuntimeException('Symlink paths are not supported.');
            }
            $check = dirname($check);
        }
        $resolved = realpath($candidate);
        if (false === $resolved || ($resolved !== $root && !str_starts_with($resolved, $root.DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Configured paths must exist within the configuration directory.');
        }
        $relative = substr($resolved, strlen($root) + 1);
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $part) {
            if (in_array($part, self::EXCLUDED, true)) {
                throw new \RuntimeException('Configured path is excluded: '.$path);
            }
        }

        return $resolved;
    }
}
