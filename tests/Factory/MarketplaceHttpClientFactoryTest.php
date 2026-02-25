<?php

declare(strict_types=1);

namespace Four\Http\Tests\Factory;

use Four\Http\Configuration\ClientConfig;
use Four\Http\Factory\MarketplaceHttpClientFactory;
use Four\Http\Tests\TestCase;
use Psr\Http\Client\ClientInterface;

/**
 * Tests für MarketplaceHttpClientFactory (PSR-18 API)
 */
class MarketplaceHttpClientFactoryTest extends TestCase
{
    private MarketplaceHttpClientFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new MarketplaceHttpClientFactory($this->logger, $this->cache);
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

    public function testCreateRateLimiterFactory(): void
    {
        $rateLimiterFactory = $this->factory->createRateLimiterFactory('generic');

        $this->assertNotNull($rateLimiterFactory);
    }

    public function testCreateRateLimiterFactoryWithCustomConfig(): void
    {
        $customConfig = [
            'limit' => 50,
            'rate'  => ['interval' => '1 minute', 'amount' => 50],
        ];

        $rateLimiterFactory = $this->factory->createRateLimiterFactory('custom', $customConfig);

        $this->assertNotNull($rateLimiterFactory);
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

        // Keine marketplace-spezifischen Einträge mehr
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
