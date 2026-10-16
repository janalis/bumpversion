<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Changelog;

use Janalis\Bumpversion\Version\SemanticVersion;

final class KeepAChangelog
{
    private const COMPARE = '~^\[(unreleased)\]:[ \t]+(<?)([^ \t<>]+/compare/)([^ \t<>]+)\.\.\.HEAD(>?)([ \t]+.*)?$~i';

    private const CATEGORIES = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security'];

    public static function tagPrefix(string $contents, string $current): string
    {
        foreach (self::visibleLines(explode("\n", str_replace("\r\n", "\n", $contents))) as $line) {
            if (preg_match(self::COMPARE, $line, $match)) {
                $from = rawurldecode($match[4]);
                if (str_ends_with($from, $current)) {
                    return substr($from, 0, -strlen($current));
                }
            }
        }

        return '';
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function visibleLines(array $lines): array
    {
        $fence = null;
        foreach ($lines as &$line) {
            if (null !== $fence) {
                if (preg_match('/^ {0,3}'.preg_quote($fence[0], '/').'{'.strlen($fence).',}[ \t]*$/', $line)) {
                    $fence = null;
                }
                $line = '';
            } elseif (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $match)) {
                $fence = $match[1];
                $line = '';
            }
        }

        return $lines;
    }

    public function notes(string $contents, SemanticVersion $version): string
    {
        $text = str_replace("\r\n", "\n", $contents);
        $lines = explode("\n", $text);
        $visible = self::visibleLines($lines);
        $start = null;
        $end = count($lines);
        $date = null;
        $yanked = false;
        $references = [];
        foreach ($visible as $i => $line) {
            if (preg_match('/^\[[^\]]+\]:[^\n]+$/', $line)) {
                $references[] = $lines[$i];
            }
            if (preg_match('/^## \['.preg_quote((string) $version, '/').'\] - (\d{4}-\d{2}-\d{2})(?: \[YANKED\])?[ \t]*$/', $line, $match)) {
                if (null !== $start) {
                    throw new \RuntimeException('Duplicate release section.');
                }
                $start = $i + 1;
                $date = $match[1];
                $yanked = str_contains($line, '[YANKED]');
            } elseif (null !== $start && $end === count($lines) && preg_match('/^##[ \t]+|^\[[^\]]+\]:/', $line)) {
                $end = $i;
            }
        }
        if (null === $start || null === $date) {
            throw new \RuntimeException('No dated changelog section exists for this release.');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \RuntimeException('The release date is invalid.');
        }
        $body = trim(implode("\n", array_slice($lines, $start, $end - $start)));
        if ('' === $body) {
            throw new \RuntimeException('The release must have curated changelog entries.');
        }

        return '# '.$version.' - '.$date.($yanked ? ' [YANKED]' : '')."\n\n".$body."\n".([] !== $references ? "\n".implode("\n", $references)."\n" : '');
    }

    public function release(string $contents, SemanticVersion $version, string $date, string $tagPrefix = ''): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Release date must be a valid YYYY-MM-DD date.');
        }
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $text = str_replace("\r\n", "\n", $contents);
        $lines = explode("\n", $text);
        $visible = self::visibleLines($lines);
        $unreleased = [];
        foreach ($visible as $i => $line) {
            if (preg_match('/^##[ \t]+(?:\[Unreleased\]|Unreleased)[ \t]*$/i', $line)) {
                $unreleased[] = $i;
            } elseif (preg_match('/^##[ \t]+\[?([^\] \t]+)\]?(?:[ \t]+-|[ \t]*$)/', $line, $match)) {
                if ($match[1] === (string) $version) {
                    throw new \RuntimeException('Changelog already contains the target release.');
                }
            }
        }
        if (1 !== count($unreleased)) {
            throw new \RuntimeException('Changelog requires exactly one ## [Unreleased] heading.');
        }
        $start = $unreleased[0];
        $end = count($lines);
        for ($i = $start + 1; $i < count($lines); ++$i) {
            if (preg_match('/^##[ \t]+|^\[[^\]]+\]:[ \t]+/', $visible[$i])) {
                $end = $i;
                break;
            }
        }
        // Reject misplaced Unreleased sections rather than rewriting existing history.
        foreach (array_slice($visible, 0, $start) as $line) {
            if (preg_match('/^##[ \t]+/', $line)) {
                throw new \RuntimeException('Unreleased must precede all released versions.');
            }
        }
        $body = trim(implode("\n", array_slice($lines, $start + 1, $end - $start - 1)));
        $categories = [];
        foreach (self::visibleLines(explode("\n", $body)) as $line) {
            if (preg_match('/^###[ \t]+(.+?)[ \t]*$/', $line, $match)) {
                if (!in_array($match[1], self::CATEGORIES, true) || in_array($match[1], $categories, true)) {
                    throw new \RuntimeException('Use unique Keep a Changelog categories: '.implode(', ', self::CATEGORIES).'.');
                }
                $categories[] = $match[1];
            }
        }
        if ('' !== $body && [] === $categories) {
            throw new \RuntimeException('Group Unreleased entries under Keep a Changelog categories.');
        }
        $bodyLines = explode("\n", $body);
        $sectionStarts = [];
        foreach (self::visibleLines($bodyLines) as $i => $line) {
            if (preg_match('/^###[ \t]+/', $line)) {
                $sectionStarts[] = $i;
            }
        }
        if ([] !== $sectionStarts) {
            $parts = [trim(implode("\n", array_slice($bodyLines, 0, $sectionStarts[0])))];
            foreach ($sectionStarts as $i => $sectionStart) {
                $sectionEnd = $sectionStarts[$i + 1] ?? count($bodyLines);
                $sectionBody = trim(implode("\n", array_slice($bodyLines, $sectionStart + 1, $sectionEnd - $sectionStart - 1)));
                if ('' !== $sectionBody) {
                    $parts[] = $bodyLines[$sectionStart]."\n\n".$sectionBody;
                }
            }
            $body = implode("\n\n", array_filter($parts, static fn (string $part) => '' !== $part));
        }
        $replacement = ['## [Unreleased]', '', "## [$version] - $date", ''];
        if ('' !== $body) {
            $replacement[] = $body;
            $replacement[] = '';
        }
        $updated = implode("\n", [...array_slice($lines, 0, $start), ...$replacement, ...array_slice($lines, $end)]);
        $target = (string) $version;
        $output = explode("\n", $updated);
        foreach (self::visibleLines($output) as $i => $line) {
            if (preg_match('/^\['.preg_quote($target, '/').'\]:/', $line)) {
                throw new \RuntimeException('Changelog already defines the target release link.');
            }
            if (preg_match(self::COMPARE, $line, $match)) {
                $tag = rawurlencode($tagPrefix.$target);
                $open = $match[2];
                $close = $match[5];
                $output[$i] = '['.$match[1].']: '.$open.$match[3].$tag.'...HEAD'.$close.($match[6] ?? '')."\n".'['.$target.']: '.$open.$match[3].$match[4].'...'.$tag.$close;
            }
        }
        $updated = implode("\n", $output);

        return str_replace("\n", $eol, $updated);
    }
}
