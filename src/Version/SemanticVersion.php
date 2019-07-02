<?php

declare(strict_types=1);

namespace App\Version;

/**
 * @see https://semver.org/
 */
class SemanticVersion implements VersionInterface
{
    public const MAJOR = 'major';
    public const MINOR = 'minor';
    public const PATCH = 'patch';

    /**
     * @var int
     */
    private $major;

    /**
     * @var int
     */
    private $minor;

    /**
     * @var int
     */
    private $patch;

    /**
     * @var string
     */
    private $preReleaseIdentifier;

    public function __construct(int $major, int $minor, int $patch, ?string $preReleaseIdentifier = null)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
        $this->preReleaseIdentifier = $preReleaseIdentifier;
    }

    public function __toString(): string
    {
        return sprintf(
            '%d.%d.%d%s',
            $this->major,
            $this->minor,
            $this->patch,
            $this->preReleaseIdentifier ? "-{$this->preReleaseIdentifier}" : ''
        );
    }

    public function getNextVersion(?string $type, ?string $preReleaseIdentifier = null): VersionInterface
    {
        // if no type has been defined and a pre-release identifier has been introduced
        if (!$type && $preReleaseIdentifier && !$this->preReleaseIdentifier) {
            return new static($this->major, $this->minor, $this->patch, "$preReleaseIdentifier.1");
        }

        // if no type has been defined and the pre-release identifier is the same
        if (!$type && $preReleaseIdentifier && $this->preReleaseIdentifier && 0 === strpos($this->preReleaseIdentifier, $preReleaseIdentifier)) {
            $suffix = substr($this->preReleaseIdentifier, strlen($preReleaseIdentifier));
            $number = (int) filter_var($suffix, FILTER_SANITIZE_NUMBER_INT) + 1;

            return new static($this->major, $this->minor, $this->patch, "$preReleaseIdentifier.$number");
        }

        // if a type is defined
        $add = (int) ($type && ($preReleaseIdentifier || !$this->preReleaseIdentifier || (!$preReleaseIdentifier && $this->preReleaseIdentifier)));
        switch ($type) {
            case static::MAJOR:
                return new static($this->major + $add, $add ? 0 : $this->minor, $add ? 0 : $this->patch, $preReleaseIdentifier);
            case static::MINOR:
                return new static($this->major, $this->minor + $add, $add ? 0 : $this->patch, $preReleaseIdentifier);
            case static::PATCH:
                return new static($this->major, $this->minor, $this->patch + $add, $preReleaseIdentifier);
        }

        // if no type has been defined and the pre-release identifier is different
        return new static($this->major, $this->minor, $this->patch, $preReleaseIdentifier ? "$preReleaseIdentifier.1" : null);
    }

    public function isPreRelease(): bool
    {
        return (bool) $this->preReleaseIdentifier;
    }

    public static function parse(string $version): VersionInterface
    {
        if (!preg_match('/^(0|(?:[1-9]\d*))(?:\.(0|(?:[1-9]\d*))(?:\.(0|(?:[1-9]\d*)))?(?:\-([\w][\w\.\-_]*))?)?$/', $version, $matches)) {
            throw new \RuntimeException(sprintf('Cannot parse version "%s"', $version));
        }

        return new static(
            (int) $matches[1],
            (int) $matches[2],
            (int) $matches[3],
            $matches[4] ?? null
        );
    }
}
