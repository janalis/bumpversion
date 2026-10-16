<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Release;

use Janalis\Bumpversion\Changelog\KeepAChangelog;
use Janalis\Bumpversion\Configuration\YamlConfiguration;
use Janalis\Bumpversion\Version\SemanticVersion;
use Janalis\Bumpversion\VersionControlSystem\GitVersionControlSystem;

final readonly class ReleaseContext
{
    public string $filename;
    public YamlConfiguration $config;
    public string $root;

    public function __construct(string $filename)
    {
        $this->filename = YamlConfiguration::resolve($filename);
        $this->config = YamlConfiguration::load($this->filename);
        $this->root = dirname($this->filename);
    }

    public function prefix(?string $override = null): string
    {
        if (null !== $override) {
            return $override;
        }

        return null !== $this->config->changelog ? KeepAChangelog::tagPrefix($this->changelog(), (string) $this->config->version) : 'v';
    }

    public function changelog(): string
    {
        if (null === $this->config->changelog) {
            throw new \RuntimeException('A configured changelog is required.');
        }
        $path = ReleasePlanner::path($this->root, $this->config->changelog);
        if (!is_file($path)) {
            throw new \RuntimeException('Changelog must be a regular file.');
        }
        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \RuntimeException('Cannot read changelog.');
        }

        return $contents;
    }

    public function notes(?SemanticVersion $version = null): string
    {
        return (new KeepAChangelog())->notes($this->changelog(), $version ?? $this->config->version);
    }

    public function commit(?string $override = null): string
    {
        $sha = $override ?? trim((new GitVersionControlSystem($this->root))->run(['rev-parse', 'HEAD']));
        if (!preg_match('/^[a-f0-9]{40}$/D', $sha)) {
            throw new \InvalidArgumentException('Expected a full release commit SHA.');
        }

        return $sha;
    }

    public function package(?string $override = null): string
    {
        $package = $override ?? $this->config->publishing['packagist_package'] ?? null;
        if (null === $package && is_file($this->root.'/composer.json')) {
            $data = json_decode((string) file_get_contents($this->root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            $package = $data['name'] ?? null;
        }
        if (!is_string($package) || !preg_match('~^[a-z0-9](?:[a-z0-9_.-]*)/[a-z0-9](?:[a-z0-9_.-]*)$~D', $package)) {
            throw new \InvalidArgumentException('Provide a valid Packagist package name.');
        }

        return $package;
    }
}
