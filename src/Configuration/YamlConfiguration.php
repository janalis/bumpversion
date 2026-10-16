<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Configuration;

use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Yaml\Yaml;

final class YamlConfiguration
{
    /** @var array<string, mixed> */
    private array $original = [];

    /**
     * @param list<string>          $paths
     * @param array<string, string> $publishing
     */
    public function __construct(public array $paths, public SemanticVersion $version, public ?string $changelog = null, public array $publishing = [])
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

        $publishing = $config['publishing'] ?? [];
        if (!is_array($publishing)) {
            throw new \InvalidArgumentException('Publishing settings must be a mapping.');
        }
        foreach ($publishing as $key => $value) {
            if (!in_array($key, ['github_repository', 'packagist_package', 'http_client'], true) || !is_string($value) || '' === trim($value)) {
                throw new \InvalidArgumentException('Invalid publishing setting. Store credentials in environment variables.');
            }
        }
        if (!in_array($publishing['http_client'] ?? 'auto', ['auto', 'symfony', 'guzzle'], true)) {
            throw new \InvalidArgumentException('HTTP client must be auto, symfony, or guzzle.');
        }
        $result = new self($paths, SemanticVersion::parse($config['version']), $changelog, $publishing);
        $result->original = $data;

        return $result;
    }

    public function contents(): string
    {
        $data = $this->original;
        $data['bumpversion'] = array_replace($data['bumpversion'] ?? [], ['version' => (string) $this->version, 'paths' => $this->paths, 'changelog' => $this->changelog]);

        if ([] !== $this->publishing || isset($data['bumpversion']['publishing'])) {
            $data['bumpversion']['publishing'] = $this->publishing;
        }

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
