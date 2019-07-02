<?php

declare(strict_types=1);

namespace App\Version;

interface VersionInterface
{
    public function __toString(): string;

    public function getNextVersion(?string $type, ?string $preReleaseIdentifier = null): VersionInterface;

    public function isPreRelease(): bool;

    public static function parse(string $version): VersionInterface;
}
