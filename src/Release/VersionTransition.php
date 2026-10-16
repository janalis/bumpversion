<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Release;

use Janalis\Bumpversion\Version\SemanticVersion;

final class VersionTransition
{
    public static function next(SemanticVersion $current, ?string $type, ?string $preRelease, ?string $target): SemanticVersion
    {
        if (null !== $target && (null !== $type || null !== $preRelease)) {
            throw new \InvalidArgumentException('--new-version cannot be combined with --type or --pre-release.');
        }
        $next = null !== $target ? SemanticVersion::parse($target) : $current->next($type, $preRelease);
        if ($next->compare($current) <= 0) {
            throw new \InvalidArgumentException('Target version must have higher SemVer precedence than the current version.');
        }

        return $next;
    }
}
