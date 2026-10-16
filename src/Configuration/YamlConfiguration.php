<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Configuration;

use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Yaml\Yaml;

final class YamlConfiguration
{
    /** @var array<string, mixed> */
    private array $original = [];

    /** @param list<string> $paths */
    public function __construct(public array $paths, public SemanticVersion $version, public ?string $changelog = null)
    {
    }

    public static function load(string $filename): self
    {
        $data = Yaml::parseFile($filename);
        $config = is_array($data) ? ($data['bumpversion'] ?? null) : null;
        if (!is_array($config) || !is_string($config['version'] ?? null)) {
            throw new \InvalidArgumentException('Configuration requires bumpversion.version as a string.');
        }
        $paths = $config['paths'] ?? ['.'];
        if (!is_array($paths) || !array_is_list($paths) || [] === $paths) {
            throw new \InvalidArgumentException('Configuration paths must be a nonempty list.');
        }
        foreach ($paths as $path) {
            if (!is_string($path) || '' === trim($path)) {
                throw new \InvalidArgumentException('Each configured path must be a nonempty string.');
            }
        }
        $changelog = $config['changelog'] ?? null;
        if (null !== $changelog && (!is_string($changelog) || '' === trim($changelog))) {
            throw new \InvalidArgumentException('Changelog must be a nonempty path or null.');
        }

        $result = new self($paths, SemanticVersion::parse($config['version']), $changelog);
        $result->original = $data;

        return $result;
    }

    public function contents(): string
    {
        $data = $this->original;
        $data['bumpversion'] = array_replace($data['bumpversion'] ?? [], ['version' => (string) $this->version, 'paths' => $this->paths, 'changelog' => $this->changelog]);

        return Yaml::dump($data, 4);
    }

    public static function resolve(string $filename): string
    {
        if (str_starts_with($filename, '~/')) {
            $home = getenv('HOME');
            if (false === $home) {
                throw new \RuntimeException('Cannot expand home directory.');
            }
            $filename = $home.substr($filename, 1);
        }
        if (!is_file($filename) && is_file($filename.'.dist')) {
            $filename .= '.dist';
        }
        $resolved = realpath($filename);
        if (false === $resolved || !is_file($resolved) || is_link($filename)) {
            throw new \RuntimeException('Configuration file not found or is a symlink. Run bumpversion init first.');
        }

        return $resolved;
    }
}
