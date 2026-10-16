<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Publishing;

use Janalis\Bumpversion\Http\ApiClient;
use Janalis\Bumpversion\Http\ApiException;
use Janalis\Bumpversion\Version\SemanticVersion;

final class GitHubPublisher
{
    public function __construct(private readonly ApiClient $http)
    {
    }

    /** @param list<string> $files */
    public function publish(string $repository, string $tag, SemanticVersion $version, string $commit, string $notes, array $files, ?string $token, bool $dryRun = false): string
    {
        if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repository) || '' === $tag || preg_match('/[\x00-\x20\x7f]/', $tag) || !preg_match('/^[a-f0-9]{40}$/D', $commit) || '' === trim($notes)) {
            throw new \InvalidArgumentException('Provide a valid repository, tag, commit, and curated release notes.');
        }
        $assets = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!is_file($file) || is_link($file) || !is_readable($file) || isset($assets[$name])) {
                throw new \InvalidArgumentException('Assets must be readable regular files with unique names.');
            }
            $hash = hash_file('sha256', $file);
            $size = filesize($file);
            if (false === $hash || false === $size) {
                throw new \RuntimeException('Cannot read release asset.');
            }
            $assets[$name] = ['path' => $file, 'size' => $size, 'hash' => $hash];
        }
        $publicUrl = 'https://github.com/'.$repository.'/releases/tag/'.rawurlencode($tag);
        if ($dryRun) {
            return $publicUrl;
        }
        if (null === $token || '' === $token) {
            throw new \InvalidArgumentException('Set GH_TOKEN or GITHUB_TOKEN to publish to GitHub.');
        }
        $headers = ['Authorization' => 'Bearer '.$token, 'X-GitHub-Api-Version' => '2022-11-28'];
        $base = 'https://api.github.com/repos/'.$repository;
        $reference = $this->http->json('GET', $base.'/git/ref/tags/'.rawurlencode($tag), $headers);
        $object = $reference['object'] ?? [];
        for ($depth = 0; ($object['type'] ?? '') === 'tag' && $depth < 8; ++$depth) {
            $sha = $this->sha($object['sha'] ?? null);
            $annotation = $this->http->json('GET', $base.'/git/tags/'.$sha, $headers);
            $object = $annotation['object'] ?? [];
        }
        if (($object['type'] ?? '') !== 'commit' || ($object['sha'] ?? '') !== $commit) {
            throw new \RuntimeException('GitHub tag does not reference the expected release commit.');
        }
        try {
            $release = $this->http->json('GET', $base.'/releases/tags/'.rawurlencode($tag), $headers);
        } catch (ApiException $exception) {
            if (404 !== $exception->status) {
                throw $exception;
            }
            $release = $this->http->json('POST', $base.'/releases', $headers, ['tag_name' => $tag, 'target_commitish' => $commit, 'name' => $tag, 'body' => $notes, 'draft' => true, 'prerelease' => null !== $version->preRelease]);
        }
        $id = $this->id($release['id'] ?? null);
        if (($release['tag_name'] ?? '') !== $tag || !is_bool($release['draft'] ?? null)) {
            throw new \RuntimeException('Invalid GitHub release response.');
        }
        $draft = $release['draft'];
        if ($draft && (($release['body'] ?? '') !== $notes || ($release['prerelease'] ?? null) !== (null !== $version->preRelease))) {
            throw new \RuntimeException('An existing draft has different release metadata. Inspect it before retrying.');
        }
        $existing = [];
        for ($page = 1;; ++$page) {
            $batch = $this->http->json('GET', $base.'/releases/'.$id.'/assets?per_page=100&page='.$page, $headers);
            foreach ($batch as $asset) {
                if (!is_array($asset) || !is_string($asset['name'] ?? null) || isset($existing[$asset['name']])) {
                    throw new \RuntimeException('Invalid or duplicate GitHub release asset.');
                }
                $existing[$asset['name']] = $asset;
            }
            if (count($batch) < 100) {
                break;
            }
            if ($page >= 100) {
                throw new \RuntimeException('Too many GitHub release assets.');
            }
        }
        // Verify all existing assets before performing uploads or metadata changes.
        foreach ($assets as $name => $asset) {
            if (isset($existing[$name])) {
                $this->verifyAsset($base, $existing[$name], $asset['hash'], $asset['size'], $headers);
            } elseif (!$draft) {
                throw new \RuntimeException('Published releases are immutable; a requested asset is missing.');
            }
        }
        foreach ($assets as $name => $asset) {
            if (isset($existing[$name])) {
                continue;
            }
            $stream = fopen($asset['path'], 'rb');
            if (false === $stream) {
                throw new \RuntimeException('Cannot open release asset.');
            }
            try {
                $digest = hash_init('sha256');
                hash_update_stream($digest, $stream);
                if (hash_final($digest) !== $asset['hash'] || !rewind($stream)) {
                    throw new \RuntimeException('Asset changed after validation.');
                }
                // Construct trusted upload URLs rather than accepting arbitrary response-provided hosts.
                $response = $this->http->request('POST', 'https://uploads.github.com/repos/'.$repository.'/releases/'.$id.'/assets?name='.rawurlencode($name), $headers + ['Content-Type' => 'application/octet-stream', 'Content-Length' => (string) $asset['size']], $stream);
                try {
                    $remote = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    throw new \RuntimeException('Cannot read uploaded asset metadata. The release remains a draft.');
                } finally {
                    $response->getBody()->close();
                }
                if (!is_array($remote) || ($remote['name'] ?? null) !== $name) {
                    throw new \RuntimeException('Invalid uploaded asset metadata.');
                }
                $this->verifyAsset($base, $remote, $asset['hash'], $asset['size'], $headers);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
        if ($draft) {
            $this->http->json('PATCH', $base.'/releases/'.$id, $headers, ['draft' => false, 'name' => $tag]);
        } elseif (($release['name'] ?? '') !== $tag) {
            // Repair display titles without modifying published notes or assets.
            $this->http->json('PATCH', $base.'/releases/'.$id, $headers, ['name' => $tag]);
        }

        return $publicUrl;
    }

    /**
     * @param array<mixed>          $asset
     * @param array<string, string> $headers
     */
    private function verifyAsset(string $base, array $asset, string $hash, int $size, array $headers): void
    {
        if (($asset['size'] ?? null) !== $size || ($asset['state'] ?? 'uploaded') !== 'uploaded') {
            throw new \RuntimeException('Release asset size or state differs; refusing replacement.');
        }
        $digest = $asset['digest'] ?? null;
        if (null === $digest) {
            $response = $this->http->request('GET', $base.'/releases/assets/'.$this->id($asset['id'] ?? null), array_replace($headers, ['Accept' => 'application/octet-stream']), null, [302]);
            if (302 === $response->getStatusCode()) {
                $location = $response->getHeaderLine('Location');
                $response->getBody()->close();
                $host = parse_url($location, PHP_URL_HOST);
                if (!in_array($host, ['release-assets.githubusercontent.com', 'objects.githubusercontent.com'], true)) {
                    throw new \RuntimeException('Untrusted GitHub asset redirect.');
                }
                // Signed asset downloads never receive the GitHub API token.
                $response = $this->http->request('GET', $location);
            }
            $body = $response->getBody();
            try {
                $context = hash_init('sha256');
                while (!$body->eof()) {
                    hash_update($context, $body->read(8192));
                }
                $digest = 'sha256:'.hash_final($context);
            } catch (\Throwable) {
                throw new \RuntimeException('Cannot verify release asset download.');
            } finally {
                $body->close();
            }
        }
        if (!is_string($digest) || !hash_equals('sha256:'.$hash, $digest)) {
            throw new \RuntimeException('Release asset checksum differs; refusing replacement.');
        }
    }

    private function id(mixed $value): string
    {
        if (!is_int($value) || $value <= 0) {
            throw new \RuntimeException('Invalid GitHub resource ID.');
        }

        return (string) $value;
    }

    private function sha(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{40}$/D', $value)) {
            throw new \RuntimeException('Invalid GitHub object SHA.');
        }

        return $value;
    }
}
