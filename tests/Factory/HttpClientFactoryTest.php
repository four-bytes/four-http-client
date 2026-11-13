<?php

declare(strict_types=1);

namespace Four\Http\Tests\Factory;

use Four\Http\Configuration\ClientConfig;
use Four\Http\Factory\HttpClientFactory;
use Four\Http\Tests\TestCase;
use Four\Http\Transport\DiscoveryHttpTransport;
use Http\Discovery\ClassDiscovery;
use Http\Discovery\Strategy\DiscoveryStrategy;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests for HttpClientFactory (PSR-18 API)
 */
class HttpClientFactoryTest extends TestCase
{
    private HttpClientFactory $factory;

    /** @var list<string>
     * @phpstan-ignore property.onlyWritten
     */
    private static array $originalStrategies = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Register the test PSR-18 client as a discovery candidate
        ClassDiscovery::prependStrategy(TestPsr18ClientStrategy::class);

        $this->factory = new HttpClientFactory($this->logger);
    }

    protected function tearDown(): void
    {
        // Clear the discovery cache so that other tests are not affected
        ClassDiscovery::clearCache();
        parent::tearDown();
    }

    public function testCreateReturnsPsr18Client(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withTimeout(30.0)
            ->build();

        $client = $this->factory->create($config);

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreateWithLoggingMiddleware(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withMiddleware(['logging'])
            ->build();

        $client = $this->factory->create($config);

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreateWithRetryMiddleware(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withRetries(new \Four\Http\Configuration\RetryConfig())
            ->build();

        $client = $this->factory->create($config);

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreateWithCustomHeaders(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withHeaders([
                'X-Custom-Header' => 'custom-value',
                'User-Agent'      => 'Test-Client/1.0',
            ])
            ->build();

        $client = $this->factory->create($config);

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testGetAvailableMiddleware(): void
    {
        $middleware = $this->factory->getAvailableMiddleware();

        $this->assertContains('logging', $middleware);
        $this->assertContains('rate_limiting', $middleware);
        $this->assertContains('retry', $middleware);
    }

    public function testGetAvailableMiddlewareDoesNotContainMarketplaceSpecific(): void
    {
        $middleware = $this->factory->getAvailableMiddleware();

        $this->assertNotContains('amazon', $middleware);
        $this->assertNotContains('ebay', $middleware);
        $this->assertNotContains('discogs', $middleware);
        $this->assertNotContains('bandcamp', $middleware);
    }

    public function testCreateWithMultipleMiddleware(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withMiddleware(['logging', 'retry'])
            ->withTimeout(45.0)
            ->build();

        $client = $this->factory->create($config);

        $this->assertInstanceOf(ClientInterface::class, $client);
    }
}

/**
 * Test-only discovery strategy for a minimal PSR-18 client.
 */
class TestPsr18ClientStrategy implements DiscoveryStrategy
{
    /** @return list<array{class: class-string, condition: class-string}> */
    public static function getCandidates($type): array
    {
        if ($type === ClientInterface::class) {
            return [
                [
                    'class' => NullPsr18Client::class,
                    'condition' => NullPsr18Client::class,
                ],
            ];
        }

        return [];
    }
}

/**
 * Minimal PSR-18 client for tests — always returns 200 OK.
 */
class NullPsr18Client implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response(200, [], '{}');
    }
}
