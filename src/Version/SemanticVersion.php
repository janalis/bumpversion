<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Version;

final readonly class SemanticVersion implements \Stringable
{
    public string $major;
    public string $minor;
    public string $patch;

    public function __construct(
        int|string $major,
        int|string $minor,
        int|string $patch,
        public ?string $preRelease = null,
        public ?string $build = null,
    ) {
        $this->major = (string) $major;
        $this->minor = (string) $minor;
        $this->patch = (string) $patch;
        self::validate((string) $this);
    }

    public function __toString(): string
    {
        return "$this->major.$this->minor.$this->patch".(null !== $this->preRelease ? '-'.$this->preRelease : '').(null !== $this->build ? '+'.$this->build : '');
    }

    public static function parse(string $version): self
    {
        $parts = self::validate($version);

        return new self($parts[1], $parts[2], $parts[3], $parts[4] ?? null, $parts[5] ?? null);
    }

    /** @return array<int, string> */
    private static function validate(string $version): array
    {
        $identifier = '(?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*)';
        if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-('.$identifier.'(?:\.'.$identifier.')*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/D', $version, $parts, PREG_UNMATCHED_AS_NULL)) {
            throw new \InvalidArgumentException('Invalid semantic version: '.$version);
        }

        return array_filter($parts, static fn ($part) => null !== $part);
    }

    private static function increment(string $value): string
    {
        for ($i = strlen($value) - 1; $i >= 0; --$i) {
            if ('9' !== $value[$i]) {
                $value[$i] = (string) ((int) $value[$i] + 1);

                return $value;
            }
            $value[$i] = '0';
        }

        return '1'.$value;
    }

    private static function compareNumbers(string $a, string $b): int
    {
        return (strlen($a) <=> strlen($b)) ?: (strcmp($a, $b) <=> 0);
    }

    public function compare(self $other): int
    {
        foreach (['major', 'minor', 'patch'] as $part) {
            $result = self::compareNumbers($this->$part, $other->$part);
            if (0 !== $result) {
                return $result;
            }
        }
        if ($this->preRelease === $other->preRelease) {
            return 0;
        }
        if (null === $this->preRelease || null === $other->preRelease) {
            return null === $this->preRelease ? 1 : -1;
        }
        $a = explode('.', $this->preRelease);
        $b = explode('.', $other->preRelease);
        foreach ($a as $i => $identifier) {
            if (!isset($b[$i])) {
                return 1;
            }
            $aNumeric = ctype_digit($identifier);
            $bNumeric = ctype_digit($b[$i]);
            $result = $aNumeric && $bNumeric ? self::compareNumbers($identifier, $b[$i]) : ($aNumeric !== $bNumeric ? ($aNumeric ? -1 : 1) : (strcmp($identifier, $b[$i]) <=> 0));
            if (0 !== $result) {
                return $result;
            }
        }

        return count($a) <=> count($b);
    }

    public function next(?string $type = null, ?string $preRelease = null): self
    {
        if (null !== $type && !in_array($type, ['major', 'minor', 'patch'], true)) {
            throw new \InvalidArgumentException('Type must be major, minor, or patch.');
        }
        if (null !== $preRelease && !preg_match('/^[A-Za-z-][0-9A-Za-z-]*$/D', $preRelease)) {
            throw new \InvalidArgumentException('Prerelease must be a named identifier such as alpha, beta, or rc. Use --new-version for other SemVer identifiers.');
        }
        if (null === $type && null === $this->preRelease) {
            $type = 'patch';
        }
        [$major, $minor, $patch] = [$this->major, $this->minor, $this->patch];
        match ($type) {
            'major' => [$major, $minor, $patch] = [self::increment($major), '0', '0'],
            'minor' => [$minor, $patch] = [self::increment($minor), '0'],
            'patch' => $patch = self::increment($patch),
            null => null,
        };
        $pre = null;
        if (null !== $preRelease) {
            $number = '1';
            if (null === $type && preg_match('/^'.preg_quote($preRelease, '/').'\.([0-9]+)$/D', $this->preRelease ?? '', $matches)) {
                $number = self::increment($matches[1]);
            }
            $pre = "$preRelease.$number";
        }

        return new self($major, $minor, $patch, $pre);
    }
}
