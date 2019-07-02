<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Version\VersionInterface;

abstract class Configuration implements ConfigurationInterface
{
    /**
     * @var string
     */
    protected $changelog;

    /**
     * @var array
     */
    protected $paths;

    /**
     * @var VersionInterface|null
     */
    protected $version;

    public function __construct(array $paths, VersionInterface $version, ?string $changelog)
    {
        $this->changelog = $changelog;
        $this->paths = $paths;
        $this->version = $version;
    }

    abstract public static function parseFile(string $filename, string $versioning): ConfigurationInterface;

    abstract public function save(string $filename): void;

    public function getChangelog(): ?string
    {
        return $this->changelog;
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getVersion(): ?VersionInterface
    {
        return $this->version;
    }

    public function setVersion(VersionInterface $version): void
    {
        $this->version = $version;
    }
}
