<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Publishing;

use Janalis\Bumpversion\Http\ApiClient;
use Janalis\Bumpversion\Http\ApiException;
use Janalis\Bumpversion\Version\SemanticVersion;

final class PackagistPublisher
{
    private \Closure $clock;
    private \Closure $sleep;

    public function __construct(private readonly ApiClient $http, ?\Closure $clock = null, ?\Closure $sleep = null)
    {
        $this->clock = $clock ?? static fn () => microtime(true);
        $this->sleep = $sleep ?? static fn (int $seconds) => sleep($seconds);
    }

    public function publish(string $package, SemanticVersion $version, string $commit, int $timeout = 300, ?string $username = null, ?string $token = null, bool $dryRun = false): void
    {
        if (!preg_match('~^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$~D', $package) || !preg_match('/^[a-f0-9]{40}$/D', $commit) || $timeout < 0 || $timeout > 3600) {
            throw new \InvalidArgumentException('Invalid package, release commit, or polling timeout.');
        }
        if ((null === $username) !== (null === $token)) {
            throw new \InvalidArgumentException('Set both PACKAGIST_USERNAME and PACKAGIST_TOKEN, or neither to use webhook indexing.');
        }
        if ($dryRun) {
            return;
        }
        if (null !== $username && null !== $token) {
            $result = $this->http->json('POST', 'https://packagist.org/api/update-package', ['Authorization' => 'Bearer '.$username.':'.$token], ['repository' => 'https://packagist.org/packages/'.$package]);
            if (($result['status'] ?? null) !== 'success') {
                throw new \RuntimeException('Packagist rejected the indexing request.');
            }
        }
        $deadline = ($this->clock)() + $timeout;
        do {
            try {
                $data = $this->http->json('GET', 'https://repo.packagist.org/p2/'.$package.'.json');
            } catch (ApiException $exception) {
                if (404 !== $exception->status) {
                    throw $exception;
                }
                $data = [];
            }
            $previous = [];
            foreach ($data['packages'][$package] ?? [] as $entry) {
                if (!is_array($entry)) {
                    throw new \RuntimeException('Invalid Packagist metadata.');
                }
                if (($data['minified'] ?? null) !== 'composer/2.0') {
                    $previous = [];
                }
                foreach ($entry as $key => $value) {
                    if ('__unset' === $value) {
                        unset($previous[$key]);
                    } else {
                        $previous[$key] = $value;
                    }
                }
                if (ltrim($previous['version'] ?? '', 'v') === (string) $version) {
                    if (($previous['source']['reference'] ?? null) !== $commit) {
                        throw new \RuntimeException('Packagist version references a different release commit.');
                    }

                    return;
                }
            }
            $remaining = $deadline - ($this->clock)();
            if ($remaining <= 0) {
                break;
            }
            ($this->sleep)((int) min(10, max(1, ceil($remaining))));
        } while (($this->clock)() <= $deadline);
        throw new \RuntimeException('Packagist has not indexed the release. Check the webhook and retry.');
    }
}
