<?php

declare(strict_types=1);

namespace App\Tests\VersionControlSystem;

use App\VersionControlSystem\VersionControlSystemInterface;
use App\VersionControlSystem\VersionControlSystemRepository;
use PHPUnit\Framework\TestCase;

/**
 * @group vcs
 * @group vcs.repository
 */
class VersionControlSystemRepositoryTest extends TestCase
{
    /**
     * @test
     */
    public function shouldFindVersionControlSystem(): void
    {
        $repo = new VersionControlSystemRepository([
            new class implements VersionControlSystemInterface
            {
                public function getChanges(?string $from = null, ?string $to = null): array
                {
                    return [];
                }

                public function getIdentifier(): string
                {
                    return 'foo';
                }

                public function getVersions(string $versioning): array
                {
                    return [];
                }

                public function isDirty(): bool
                {
                    return false;
                }

                public function release(string $currentVersion, string $nextVersion, ?string $description = null): void
                {
                    // do nothing
                }
            },
        ]);
        $this->assertNotEmpty($repo->find('foo'));
        $this->assertEmpty($repo->find('bar'));
    }

    /**
     * @test
     */
    public function shouldGetIdentifiers(): void
    {
        $repo = new VersionControlSystemRepository([
            new class implements VersionControlSystemInterface
            {
                public function getChanges(?string $from = null, ?string $to = null): array
                {
                    return [];
                }

                public function getIdentifier(): string
                {
                    return 'foo';
                }

                public function getVersions(string $versioning): array
                {
                    return [];
                }

                public function isDirty(): bool
                {
                    return false;
                }

                public function release(string $currentVersion, string $nextVersion, ?string $description = null): void
                {
                    // do nothing
                }
            },
            new class implements VersionControlSystemInterface
            {
                public function getChanges(?string $from = null, ?string $to = null): array
                {
                    return [];
                }

                public function getIdentifier(): string
                {
                    return 'bar';
                }

                public function getVersions(string $versioning): array
                {
                    return [];
                }

                public function isDirty(): bool
                {
                    return false;
                }

                public function release(string $currentVersion, string $nextVersion, ?string $description = null): void
                {
                    // do nothing
                }
            },
        ]);
        $this->assertSame(['foo', 'bar'], $repo->getIdentifiers());
    }
}
