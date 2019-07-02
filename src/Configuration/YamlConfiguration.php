<?php

declare(strict_types=1);

namespace App\Configuration;

use Symfony\Component\Yaml\Yaml;

class YamlConfiguration extends Configuration
{
    public static function parseFile(string $filename, string $versioning): ConfigurationInterface
    {
        $config = Yaml::parseFile($filename);
        if (empty($config['bumpversion'])) {
            throw new \RuntimeException('Empty bumpversion configuration');
        }

        $changelog = $config['bumpversion']['changelog'] ?? null;
        $paths = $config['bumpversion']['paths'] ?? ['.'];
        $version = $config['bumpversion']['version'] ? call_user_func(sprintf('%s::%s', $versioning, 'parse'), $config['bumpversion']['version']) : null;

        return new self($paths, $version, $changelog);
    }

    public function save(string $filename): void
    {
        file_put_contents($filename, Yaml::dump([
            'bumpversion' => array_filter([
                'changelog' => $this->changelog,
                'version' => (string) $this->version,
                'paths' => $this->paths,
            ]),
        ], 3));
    }
}
