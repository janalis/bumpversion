<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BumpCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * @group command
 * @group command.bump
 */
class BumpCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $tempdir = sprintf('%s/phpunit/bumpversion_bump', sys_get_temp_dir());
        if (@mkdir($tempdir, 0777, true) && !file_exists($tempdir)) {
            throw new \RuntimeException("Cannot create directory '$tempdir'.");
        }
        chdir($tempdir);
        file_put_contents('bumpversion.yaml', Yaml::dump([
            'bumpversion' => [
                'changelog' => null,
                'version' => '1.0.0',
                'paths' => [
                    'README.md',
                ],
            ],
        ]));
        file_put_contents('README.md', '1.0.0');
        if (!file_exists('src')) {
            mkdir('src');
        }
    }

    /**
     * @test
     */
    public function shouldBumpVersion(): void
    {
        $command = new BumpCommand();
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('1.0.1', file_get_contents('README.md'));
        $command = new BumpCommand();
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute([
            '--type' => 'minor',
            '--pre-release' => 'alpha',
        ]);

        $this->assertStringContainsString('1.1.0-alpha', file_get_contents('README.md'));

        $command = new BumpCommand();
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute([
            '--pre-release' => 'beta',
        ]);

        $this->assertStringContainsString('1.1.0-beta', file_get_contents('README.md'));

        $command = new BumpCommand();
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute([
            '--pre-release' => 'beta',
        ]);

        $this->assertStringContainsString('1.1.0-beta.2', file_get_contents('README.md'));

        $command = new BumpCommand();
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('1.1.0', file_get_contents('README.md'));

        $command = new BumpCommand();
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute([
            '--type' => 'major',
        ]);

        $this->assertStringContainsString('2.0.0', file_get_contents('README.md'));
    }
}
