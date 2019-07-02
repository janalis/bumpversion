<?php

declare(strict_types=1);

namespace App\Tests\Configuration;

use App\Configuration\YamlConfiguration;
use App\Version\SemanticVersion;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @group configuration
 * @group configuration.yaml
 */
class YamlConfigurationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $tempdir = sprintf('%s/phpunit/bumpversion_yaml', sys_get_temp_dir());
        if (@mkdir($tempdir, 0777, true) && !file_exists($tempdir)) {
            throw new \RuntimeException("Cannot create directory '$tempdir'.");
        }
        chdir($tempdir);
        file_put_contents('bumpversion.yaml', Yaml::dump([
            'bumpversion' => [
                'changelog' => 'CHANGELOG.md',
                'version' => '1.0.0',
                'paths' => [
                    'src',
                ],
            ],
        ]));
    }

    /**
     * @test
     */
    public function shouldReadConfiguration(): void
    {
        $config = YamlConfiguration::parseFile('bumpversion.yaml', SemanticVersion::class);
        $this->assertSame('CHANGELOG.md', $config->getChangelog());
        $this->assertSame('1.0.0', (string) $config->getVersion());
        $this->assertSame(['src'], $config->getPaths());
    }
}
