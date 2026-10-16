<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\Release;

use Janalis\Bumpversion\Release\EditPlan;
use Janalis\Bumpversion\Tests\WorkspaceTestCase;

final class EditPlanTest extends WorkspaceTestCase
{
    public function testConcurrentChangeRollsBackEarlierWrites(): void
    {
        $plan = new EditPlan();
        $plan->add($this->directory.'/VERSION', '1.0.1');
        $plan->add($this->directory.'/new-file', 'new');
        $plan->add($this->directory.'/bumpversion.yaml', 'changed');
        file_put_contents($this->directory.'/bumpversion.yaml', 'concurrent change');
        try {
            $plan->apply();
            self::fail('Expected failure');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Earlier writes restored', $exception->getMessage());
        }
        self::assertSame("1.0.0\n", file_get_contents($this->directory.'/VERSION'));
        self::assertFileDoesNotExist($this->directory.'/new-file');
        self::assertSame('concurrent change', file_get_contents($this->directory.'/bumpversion.yaml'));
    }
}
