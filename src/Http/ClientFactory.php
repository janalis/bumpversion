<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Http;

use Http\Discovery\Psr18ClientDiscovery;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

final class ClientFactory
{
    public static function create(string $selection = 'auto', ?string $root = null): ClientInterface
    {
        if (!in_array($selection, ['auto', 'symfony', 'guzzle'], true)) {
            throw new \InvalidArgumentException('HTTP client must be auto, symfony, or guzzle.');
        }
        if ('auto' === $selection) {
            $requires = [];
            if (null !== $root && is_file($root.'/composer.json')) {
                $data = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
                $requires = array_replace($data['require'] ?? [], $data['require-dev'] ?? []);
            }
            if (isset($requires['guzzlehttp/guzzle']) && !isset($requires['symfony/http-client']) && class_exists(\GuzzleHttp\Client::class)) {
                $selection = 'guzzle';
            } else {
                // Discovery works without its Composer plugin. Bundled defaults are always available.
                $discovered = Psr18ClientDiscovery::find();
                if (!$discovered instanceof Psr18Client && !$discovered instanceof \GuzzleHttp\Client) {
                    return $discovered;
                }
                $selection = 'symfony';
            }
        }
        if ('guzzle' === $selection) {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new \RuntimeException('Guzzle is not available. Install guzzlehttp/guzzle or select symfony.');
            }

            return new \GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10, 'allow_redirects' => false, 'http_errors' => false]);
        }
        $factory = new Psr17Factory();

        return new Psr18Client(HttpClient::create(['timeout' => 30, 'max_duration' => 30, 'max_redirects' => 0]), $factory, $factory);
    }
}
