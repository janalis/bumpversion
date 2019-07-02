<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\InitCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * @group command
 * @group command.init
 */
class InitCommandTest extends TestCase
{
    protected function setUp(): void
    {
        $tempdir = sprintf('%s/phpunit/bumpversion_init', sys_get_temp_dir());
        if (@mkdir($tempdir, 0777, true) && !file_exists($tempdir)) {
            $this->fail("Cannot create directory '$tempdir'.");
        }
        chdir($tempdir);
        touch('CHANGELOG.md');
        if (!file_exists('src')) {
            mkdir('src');
        }
    }

    /**
     * @test
     */
    public function shouldInitializeConfiguration(): void
    {
        $command = new InitCommand();
        $command->setApplication(new Application());

        $tester = new CommandTester($command);
        $tester->setInputs([
            '1.0.0',
            'CHANGELOG.md',
            'src',
            '',
        ]);
        $tester->execute([]);

        $this->assertFileExists('bumpversion.yaml');

        $conf = Yaml::parseFile('bumpversion.yaml');

        $this->assertSame('CHANGELOG.md', $conf['bumpversion']['changelog'] ?? null);
        $this->assertSame('1.0.0', $conf['bumpversion']['version'] ?? null);
        $this->assertSame(['src'], $conf['bumpversion']['paths'] ?? null);
    }
}
