<?php

declare(strict_types=1);

namespace App\VersionControlSystem;

class VersionControlSystemRepository
{
    /**
     * @var VersionControlSystemInterface[]
     */
    private $versionControlSystems;

    /**
     * Constructor.
     *
     * @param VersionControlSystemInterface[] $versionControlSystems
     */
    public function __construct(array $versionControlSystems)
    {
        $this->versionControlSystems = $versionControlSystems;
    }

    /**
     * Finds version control system by identifier.
     *
     * @param string $identifier identifier
     *
     * @return null|VersionControlSystemInterface version control system
     */
    public function find(string $identifier): ?VersionControlSystemInterface
    {
        foreach ($this->versionControlSystems as $versionControlSystem) {
            if ($identifier === $versionControlSystem->getIdentifier()) {
                return $versionControlSystem;
            }
        }

        return null;
    }

    /**
     * Gets version control systems identifiers.
     *
     * @return string[] identifiers
     */
    public function getIdentifiers(): array
    {
        return array_map(static function (VersionControlSystemInterface $vcs): string {
            return $vcs->getIdentifier();
        }, $this->versionControlSystems);
    }
}
