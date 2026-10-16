<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ApiClient
{
    private RequestFactoryInterface $requests;
    private StreamFactoryInterface $streams;
    private \Closure $sleep;

    public function __construct(private readonly ClientInterface $client, ?RequestFactoryInterface $requests = null, ?StreamFactoryInterface $streams = null, ?\Closure $sleep = null)
    {
        $this->requests = $requests ?? new Psr17Factory();
        $this->streams = $streams ?? new Psr17Factory();
        $this->sleep = $sleep ?? static fn (int $seconds) => sleep($seconds);
    }

    /**
     * @param array<string, string> $headers
     * @param resource|string|null  $body
     * @param list<int>             $acceptedStatuses
     */
    public function request(string $method, string $url, array $headers = [], mixed $body = null, array $acceptedStatuses = []): ResponseInterface
    {
        $parts = parse_url($url);
        if (false === $parts || ($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', ['api.github.com', 'uploads.github.com', 'packagist.org', 'repo.packagist.org', 'release-assets.githubusercontent.com', 'objects.githubusercontent.com'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['port']) && 443 !== $parts['port']) {
            throw new \InvalidArgumentException('Publishing requires an approved HTTPS API endpoint.');
        }
        foreach ($headers as $value) {
            if (str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new \InvalidArgumentException('Invalid HTTP header.');
            }
        }
        try {
            $request = $this->requests->createRequest($method, $url)->withHeader('User-Agent', 'Bumpversion')->withHeader('Accept', 'application/json');
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
            if (null !== $body) {
                $request = $request->withBody(is_resource($body) ? $this->streams->createStreamFromResource($body) : $this->streams->createStream((string) $body));
            }
        } catch (\Throwable) {
            throw new \RuntimeException('Cannot construct publishing request.');
        }
        for ($attempt = 0;; ++$attempt) {
            try {
                $response = $this->client->sendRequest($request);
            } catch (\Throwable) {
                // Never propagate transport messages: they can contain URLs, headers, or tokens.
                if ('GET' !== $method || $attempt >= 2) {
                    throw new \RuntimeException('Publishing network request failed. Check connectivity and TLS configuration.');
                }
                ($this->sleep)(1 + $attempt);
                continue;
            }
            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300 || in_array($status, $acceptedStatuses, true)) {
                return $response;
            }
            if ('GET' === $method && $attempt < 2 && (429 === $status || $status >= 500)) {
                $retry = $response->getHeaderLine('Retry-After');
                $seconds = ctype_digit($retry) ? min(30, (int) $retry) : 1 + $attempt;
                $response->getBody()->close();
                ($this->sleep)($seconds);
                continue;
            }
            $response->getBody()->close();
            throw new ApiException($status);
        }
    }

    /**
     * @param array<string, string> $headers
     * @param array<mixed>|null     $data
     *
     * @return array<mixed>
     */
    public function json(string $method, string $url, array $headers = [], ?array $data = null): array
    {
        if (null !== $data) {
            $headers['Content-Type'] = 'application/json';
        }
        $response = $this->request($method, $url, $headers, null !== $data ? json_encode($data, JSON_THROW_ON_ERROR) : null);
        $body = $response->getBody();
        try {
            $contents = '';
            while (!$body->eof()) {
                $contents .= $body->read(8192);
                if (strlen($contents) > 16 * 1024 * 1024) {
                    throw new \RuntimeException('Publishing API response is too large.');
                }
            }
            if ('' === $contents) {
                return [];
            }
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Invalid publishing API response.');
            }

            return $decoded;
        } catch (\Throwable) {
            throw new \RuntimeException('Cannot read publishing API response.');
        } finally {
            $body->close();
        }
    }
}
