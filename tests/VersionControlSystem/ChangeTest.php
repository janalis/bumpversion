<?php

declare(strict_types=1);

namespace App\Tests\VersionControlSystem;

use App\VersionControlSystem\Change;
use PHPUnit\Framework\TestCase;

/**
 * @group vcs
 * @group vcs.change
 */
class ChangeTest extends TestCase
{
    /**
     * @test
     */
    public function shouldConstruct(): void
    {
        $date = new \DateTime();
        $change = new Change('foo', 'bar', 'baz', $date);
        $this->assertSame('foo', $change->getIdentifier());
        $this->assertSame('bar', $change->getSubject());
        $this->assertSame('baz', $change->getAuthor());
        $this->assertSame($date, $change->getDate());
    }
}
