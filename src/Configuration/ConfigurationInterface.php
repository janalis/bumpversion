<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Version\VersionInterface;

interface ConfigurationInterface
{
    public function __construct(array $paths, VersionInterface $version, ?string $changelog);

    public static function parseFile(string $filename, string $versioning): ConfigurationInterface;

    public function save(string $filename): void;

    public function getChangelog(): ?string;

    public function getPaths(): array;

    public function getVersion(): ?VersionInterface;

    public function setVersion(VersionInterface $version): void;
}
