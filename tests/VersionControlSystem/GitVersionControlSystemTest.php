<?php

declare(strict_types=1);

namespace App\Tests\VersionControlSystem;

use App\Version\SemanticVersion;
use App\VersionControlSystem\GitVersionControlSystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @group vcs
 * @group vcs.git
 */
class GitVersionControlSystemTest extends TestCase
{
    /**
     * @var MockObject|GitVersionControlSystem
     */
    private $vcs;

    protected function setUp(): void
    {
        $this->vcs = $this->getMockBuilder(GitVersionControlSystem::class)->setMethods(['getProcess'])->getMock();
    }

    /**
     * @test
     */
    public function shouldDetectDirtyState(): void
    {
        $process = $this->getMockBuilder(Process::class)->disableOriginalConstructor()->getMock();
        $process->expects($this->once())->method('getExitCode')->willReturn(1);
        $process->expects($this->once())->method('getErrorOutput')->willReturn('');
        $this->vcs->method('getProcess')->willReturn($process);

        $this->assertTrue($this->vcs->isDirty());
    }

    /**
     * @test
     */
    public function shouldDetectCleanState(): void
    {
        $process = $this->getMockBuilder(Process::class)->disableOriginalConstructor()->getMock();
        $process->expects($this->once())->method('getExitCode')->willReturn(0);
        $process->expects($this->once())->method('getOutput')->willReturn('');
        $this->vcs->method('getProcess')->willReturn($process);

        $this->assertFalse($this->vcs->isDirty());
    }

    /**
     * @test
     */
    public function shouldGetChanges(): void
    {
        $logs = [];
        for($i = 0; $i < 20; ++$i) {
            $logs[] = sprintf(
                '%s %s by %s at %s',
                hash('sha256', "hash$i"),
                "Subject $i",
                'phpunit',
                date('r')
            );
        }
        $process = $this->getMockBuilder(Process::class)->disableOriginalConstructor()->getMock();
        $process->expects($this->once())->method('getExitCode')->willReturn(0);
        $process->expects($this->once())->method('getOutput')->willReturn(implode(PHP_EOL, $logs));
        $this->vcs->method('getProcess')->willReturn($process);

        $changes = $this->vcs->getChanges();
        $this->assertCount(20, $changes);
        $this->assertRegexp('/^[a-z0-9]{64}$/', $changes[0]->getIdentifier());
        $this->assertEquals('Subject 0', $changes[0]->getSubject());
        $this->assertEquals('phpunit', $changes[0]->getAuthor());
        $this->assertInstanceOf(\DateTimeInterface::class, $changes[0]->getDate());
    }

    /**
     * @test
     */
    public function shouldGetIdentifier(): void
    {
        $this->assertSame('git', $this->vcs->getIdentifier());
    }

    /**
     * @test
     */
    public function shouldGetProcess(): void
    {
        $vcs = new GitVersionControlSystem();
        $process = $vcs->getProcess(['ls']);
        $this->assertSame("'ls'", $process->getCommandLine());
    }

    /**
     * @test
     */
    public function shouldGetVersions(): void
    {
        $versions = [];
        $version = SemanticVersion::parse('1.0.0');
        for($i = 0; $i < 20; ++$i) {
            $versions[] = $version;
            $version = $version->getNextVersion($i % 5 === 0 ? SemanticVersion::PATCH : SemanticVersion::MINOR);
        }
        $process = $this->getMockBuilder(Process::class)->disableOriginalConstructor()->getMock();
        $process->expects($this->once())->method('getExitCode')->willReturn(0);
        $process->expects($this->once())->method('getOutput')->willReturn(implode(PHP_EOL, $versions));
        $this->vcs->method('getProcess')->willReturn($process);

        $this->assertSame(implode(PHP_EOL, $versions), implode(PHP_EOL, $this->vcs->getVersions(SemanticVersion::class)));
    }

    /**
     * @test
     *
     * @group rel
     */
    public function shouldRelease(): void
    {
        $this->vcs->expects($this->exactly(4))->method('getProcess')->withConsecutive(
            [
                $this->equalTo(['git', 'diff-index', '--quiet', 'HEAD']),
            ],
            [
                $this->equalTo(['git', 'add', '.']),
            ],
            [
                $this->equalTo(['git', 'commit', '-m', 'bump version 1.0.0 → 1.1.0']),
            ],
            [
                $this->equalTo(['git', 'tag', '1.1.0', '-m', 'foo']),
            ]
        )->willReturnOnConsecutiveCalls(
            (function() {
                $process = $this->getMockBuilder(Process::class)->disableOriginalConstructor()->getMock();
                $process->method('getExitCode')->willReturn(1);
                $process->method('getErrorOutput')->willReturn('');

                return $process;
            })(),
            (function() {
                $process = $this->getMockBuilder(Process::class)->disableOriginalConstructor()->getMock();
                $process->method('getExitCode')->willReturn(0);
                $process->method('getOutput')->willReturn('');

                return $process;
            })(),
            (function() {
                $process = $this->getMockBuilder(Process::class)->disableOriginalConstructor()->getMock();
                $process->method('getExitCode')->willReturn(0);
                $process->method('getOutput')->willReturn('');

                return $process;
            })(),
            (function() {
                $process = $this->getMockBuilder(Process::class)->disableOriginalConstructor()->getMock();
                $process->method('getExitCode')->willReturn(0);
                $process->method('getOutput')->willReturn('');

                return $process;
            })()
        );

        $this->vcs->release('1.0.0', '1.1.0', 'foo');
    }
}
