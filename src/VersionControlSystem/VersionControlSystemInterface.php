<?php

declare(strict_types=1);

namespace App\VersionControlSystem;

use App\Version\VersionInterface;

interface VersionControlSystemInterface
{
    /**
     * Gets changes in ascending order.
     *
     * @param string|null $from version number
     * @param string|null $to   version number
     *
     * @return ChangeInterface[] changes
     */
    public function getChanges(?string $from = null, ?string $to = null): array;

    /**
     * Gets vcs identifier.
     */
    public function getIdentifier(): string;

    /**
     * Gets versions in ascending order.
     *
     * @param string $versioning nomenclature
     *
     * @return VersionInterface[] versions
     */
    public function getVersions(string $versioning): array;

    /**
     * Is the repository in a dirty state?
     *
     * @return bool Yes/No
     */
    public function isDirty(): bool;

    /**
     * Releases a version.
     *
     * @param string      $currentVersion current version
     * @param string      $nextVersion    next version
     * @param string|null $description    description
     */
    public function release(string $currentVersion, string $nextVersion, ?string $description = null): void;
}
