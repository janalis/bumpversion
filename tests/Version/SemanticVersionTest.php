<?php

declare(strict_types=1);

namespace App\Tests\Version;

use App\Version\SemanticVersion;
use PHPUnit\Framework\TestCase;

/**
 * @group version
 * @group version.semantic
 */
class SemanticVersionTest extends TestCase
{
    /**
     * @test
     */
    public function shouldGetNextVersion(): void
    {
        foreach([
            ['1.1.1', '1.1.2', 'patch', null],
            ['1.1.1', '1.2.0', 'minor', null],
            ['1.1.1', '2.0.0', 'major', null],
            ['1.0.0', '1.0.0-alpha.1', null, 'alpha'],
            ['1.0.0-alpha.1', '1.0.0-beta.1', null, 'beta'],
            ['1.0.0-beta.1', '1.0.0-beta.2', null, 'beta'],
            ['1.0.0-beta.2', '1.0.0', null, null],
            ['1.0.0-beta.2', '1.1.0', 'minor', null],
        ] as [$current, $next, $type, $pre]) {
            $version = SemanticVersion::parse($current);
            $this->assertSame($next, (string) $version->getNextVersion($type, $pre));
        }
    }
}
