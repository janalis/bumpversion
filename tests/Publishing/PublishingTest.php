<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\Publishing;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Janalis\Bumpversion\Http\ApiClient;
use Janalis\Bumpversion\Http\ApiException;
use Janalis\Bumpversion\Http\ClientFactory;
use Janalis\Bumpversion\Publishing\GitHubPublisher;
use Janalis\Bumpversion\Publishing\PackagistPublisher;
use Janalis\Bumpversion\Tests\WorkspaceTestCase;
use Janalis\Bumpversion\Version\SemanticVersion;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublishingTest extends WorkspaceTestCase
{
    private const SHA = '1111111111111111111111111111111111111111';
    private array $requests = [];

    private function api(array $responses, ?\Closure $sleep = null): ApiClient
    {
        $requests = &$this->requests;
        $client = new class($responses, $requests) implements ClientInterface {
            public function __construct(private array $responses, private array &$requests)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;
                $response = array_shift($this->responses);
                if ($response instanceof \Throwable) {
                    throw $response;
                }
                if (!$response instanceof ResponseInterface) {
                    throw new \LogicException('Unexpected request in test.');
                }

                return $response;
            }
        };

        return new ApiClient($client, sleep: $sleep ?? static fn () => null);
    }

    private function json(array $data, int $status = 200): Response
    {
        return new Response($status, [], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function release(bool $draft = true): array
    {
        return ['id' => 42, 'tag_name' => 'v1.0.0', 'name' => 'v1.0.0', 'draft' => $draft, 'body' => 'Curated notes', 'prerelease' => false];
    }

    private function tag(): Response
    {
        return $this->json(['object' => ['type' => 'commit', 'sha' => self::SHA]]);
    }

    private function publish(ApiClient $api, array $assets = []): string
    {
        return (new GitHubPublisher($api))->publish('example/package', 'v1.0.0', SemanticVersion::parse('1.0.0'), self::SHA, 'Curated notes', $assets, 'synthetic-secret');
    }

    public function testDraftIsUploadedVerifiedThenPublished(): void
    {
        $file = $this->directory.'/app.phar';
        file_put_contents($file, 'binary');
        $asset = ['id' => 43, 'name' => 'app.phar', 'size' => 6, 'digest' => 'sha256:'.hash('sha256', 'binary')];
        $api = $this->api([$this->tag(), new Response(404), $this->json($this->release()), $this->json([]), $this->json($asset), $this->json($this->release(false))]);
        self::assertSame('https://github.com/example/package/releases/tag/v1.0.0', $this->publish($api, [$file]));
        self::assertSame(['GET', 'GET', 'POST', 'GET', 'POST', 'PATCH'], array_map(static fn ($r) => $r->getMethod(), $this->requests));
        self::assertSame('v1.0.0', json_decode((string) $this->requests[2]->getBody(), true)['name']);
        self::assertSame('false', json_encode(json_decode((string) $this->requests[5]->getBody(), true)['draft']));
    }

    public function testPublishedAssetsAreImmutableAndIdempotent(): void
    {
        $file = $this->directory.'/app.phar';
        file_put_contents($file, 'binary');
        $api = $this->api([$this->tag(), $this->json($this->release(false)), $this->json([['id' => 43, 'name' => 'app.phar', 'size' => 6, 'digest' => 'sha256:'.hash('sha256', 'binary')]])]);
        $this->publish($api, [$file]);
        self::assertCount(3, $this->requests);
    }

    public function testAssetMismatchNeverMutatesRelease(): void
    {
        $file = $this->directory.'/app.phar';
        file_put_contents($file, 'binary');
        $api = $this->api([$this->tag(), $this->json($this->release(false)), $this->json([['id' => 43, 'name' => 'app.phar', 'size' => 6, 'digest' => 'sha256:wrong']])]);
        try {
            $this->publish($api, [$file]);
            self::fail('Expected checksum rejection');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('checksum differs', $e->getMessage());
            self::assertCount(3, $this->requests);
        }
    }

    public function testSignedAssetDownloadDoesNotReceiveCredentials(): void
    {
        $file = $this->directory.'/app.phar';
        file_put_contents($file, 'binary');
        $api = $this->api([$this->tag(), $this->json($this->release(false)), $this->json([['id' => 43, 'name' => 'app.phar', 'size' => 6]]), new Response(302, ['Location' => 'https://release-assets.githubusercontent.com/example?signature=synthetic']), new Response(200, [], 'binary')]);
        $this->publish($api, [$file]);
        self::assertSame('Bearer synthetic-secret', $this->requests[3]->getHeaderLine('Authorization'));
        self::assertFalse($this->requests[4]->hasHeader('Authorization'));
    }

    public function testWrongTagCommitFailsBeforeReleaseMutation(): void
    {
        $api = $this->api([$this->json(['object' => ['type' => 'commit', 'sha' => str_repeat('2', 40)]])]);
        $this->expectExceptionMessage('expected release commit');
        $this->publish($api);
    }

    public function testMatchingDraftCanResumeAndOldTitleCanBeRepaired(): void
    {
        $api = $this->api([$this->tag(), $this->json($this->release()), $this->json([]), $this->json($this->release(false))]);
        $this->publish($api);
        self::assertSame('PATCH', $this->requests[3]->getMethod());
        $this->requests = [];
        $api = $this->api([$this->tag(), $this->json(array_replace($this->release(false), ['name' => 'Old prefix v1.0.0'])), $this->json([]), $this->json($this->release(false))]);
        $this->publish($api);
        self::assertSame(['name' => 'v1.0.0'], json_decode((string) $this->requests[3]->getBody(), true));
    }

    public function testUploadFailureLeavesDraftUnpublished(): void
    {
        $file = $this->directory.'/app.phar';
        file_put_contents($file, 'binary');
        $api = $this->api([$this->tag(), $this->json($this->release()), $this->json([]), new Response(422)]);
        try {
            $this->publish($api, [$file]);
            self::fail('Expected upload failure');
        } catch (ApiException $e) {
            self::assertSame(422, $e->status);
            self::assertSame(['GET', 'GET', 'GET', 'POST'], array_map(static fn ($r) => $r->getMethod(), $this->requests));
        }
    }

    public function testAnnotatedTagsArePeeledBeforePublishing(): void
    {
        $api = $this->api([$this->json(['object' => ['type' => 'tag', 'sha' => str_repeat('2', 40)]]), $this->tag(), $this->json($this->release(false)), $this->json([])]);
        $this->publish($api);
        self::assertStringContainsString('/git/tags/'.str_repeat('2', 40), (string) $this->requests[1]->getUri());
    }

    public function testDraftMetadataConflictIsRejected(): void
    {
        $api = $this->api([$this->tag(), $this->json(array_replace($this->release(), ['body' => 'Different notes']))]);
        $this->expectExceptionMessage('different release metadata');
        $this->publish($api);
    }

    public function testUntrustedEndpointsAreRejectedWithoutSending(): void
    {
        $api = $this->api([]);
        try {
            $api->request('GET', 'https://example.invalid/test', ['Authorization' => 'Bearer synthetic-secret']);
            self::fail('Expected endpoint rejection');
        } catch (\InvalidArgumentException) {
            self::assertCount(0, $this->requests);
        }
    }

    public function testPackagistPollsAndExpandsInheritedMetadata(): void
    {
        $time = 0;
        $api = $this->api([new Response(404), $this->json(['minified' => 'composer/2.0', 'packages' => ['example/package' => [['version' => '1.1.0', 'source' => ['reference' => self::SHA]], ['version' => 'v1.0.0']]]])]);
        $publisher = new PackagistPublisher($api, static function () use (&$time) { return $time; }, static function ($seconds) use (&$time) { $time += $seconds; });
        $publisher->publish('example/package', SemanticVersion::parse('1.0.0'), self::SHA, 20);
        self::assertSame(10, $time);
        self::assertCount(2, $this->requests);
    }

    public function testPackagistRefreshAndWrongCommit(): void
    {
        $api = $this->api([$this->json(['status' => 'success']), $this->json(['packages' => ['example/package' => [['version' => '1.0.0', 'source' => ['reference' => str_repeat('2', 40)]]]]])]);
        try {
            (new PackagistPublisher($api))->publish('example/package', SemanticVersion::parse('1.0.0'), self::SHA, 0, 'example', 'synthetic-secret');
            self::fail('Expected SHA rejection');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('different release commit', $e->getMessage());
            self::assertSame('Bearer example:synthetic-secret', $this->requests[0]->getHeaderLine('Authorization'));
            self::assertFalse($this->requests[1]->hasHeader('Authorization'));
        }
    }

    public function testPackagistTimeoutAndDryRun(): void
    {
        $publisher = new PackagistPublisher($this->api([$this->json([])]));
        $publisher->publish('example/package', SemanticVersion::parse('1.0.0'), self::SHA, dryRun: true);
        self::assertCount(0, $this->requests);
        $this->expectExceptionMessage('has not indexed');
        $publisher->publish('example/package', SemanticVersion::parse('1.0.0'), self::SHA, 0);
    }

    public function testRetryIsBoundedAndErrorsNeverIncludeTransportSecrets(): void
    {
        $delays = [];
        $api = $this->api([new Response(429, ['Retry-After' => '999']), new Response(503), $this->json(['ok' => true])], static function ($seconds) use (&$delays) { $delays[] = $seconds; });
        self::assertTrue($api->json('GET', 'https://api.github.com/test')['ok']);
        self::assertSame([30, 2], $delays);
        $api = $this->api([new \RuntimeException('synthetic-secret')]);
        try {
            $api->json('POST', 'https://api.github.com/test');
            self::fail('Expected transport failure');
        } catch (\RuntimeException $e) {
            self::assertStringNotContainsString('synthetic-secret', (string) $e);
            self::assertNull($e->getPrevious());
        }
    }

    public function testBothNativeAdaptersPreserveRedirectAndErrorStatus(): void
    {
        $clients = [new Psr18Client(new MockHttpClient([new MockResponse('', ['http_code' => 302]), new MockResponse('{}', ['http_code' => 401])])), new Client(['handler' => HandlerStack::create(new MockHandler([new Response(302), new Response(401, [], '{}')]))])];
        foreach ($clients as $client) {
            $api = new ApiClient($client);
            self::assertSame(302, $api->request('GET', 'https://api.github.com/test', acceptedStatuses: [302])->getStatusCode());
            try {
                $api->json('POST', 'https://api.github.com/test');
                self::fail('Expected authentication rejection');
            } catch (ApiException $e) {
                self::assertSame(401, $e->status);
            }
        }
    }

    public function testAutoSelectionAndExplicitOverrides(): void
    {
        file_put_contents($this->directory.'/composer.json', '{"require":{"guzzlehttp/guzzle":"^7"}}');
        self::assertInstanceOf(Client::class, ClientFactory::create(root: $this->directory));
        self::assertInstanceOf(Psr18Client::class, ClientFactory::create('symfony', $this->directory));
        file_put_contents($this->directory.'/composer.json', '{"require":{"guzzlehttp/guzzle":"^7","symfony/http-client":"^7"}}');
        self::assertInstanceOf(Psr18Client::class, ClientFactory::create(root: $this->directory));
        self::assertInstanceOf(Client::class, ClientFactory::create('guzzle', $this->directory));
    }
}
