<?php

declare(strict_types=1);

namespace Four\Http\Tests\Middleware;

use Four\Http\Middleware\RateLimitingMiddleware;
use Four\Http\Tests\TestCase;
use Four\Http\Transport\HttpResponseInterface;
use Four\Http\Transport\HttpTransportInterface;
use Four\RateLimit\RateLimiterInterface;

/**
 * Tests für RateLimitingMiddleware
 */
class RateLimitingMiddlewareTest extends TestCase
{
    private RateLimiterInterface $rateLimiter;
    private RateLimitingMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = $this->createMockRateLimiter();

        $this->middleware = new RateLimitingMiddleware(
            $this->rateLimiter,
            'general',
            $this->logger,
        );
    }

    /**
     * Erstellt einen einfachen Mock-RateLimiter der immer erlaubt
     */
    private function createMockRateLimiter(bool $allowed = true): RateLimiterInterface
    {
        return new class($allowed) implements RateLimiterInterface {
            public function __construct(private readonly bool $allowed) {}

            public function isAllowed(string $key, int $tokens = 1): bool { return $this->allowed; }

            public function waitForAllowed(string $key, int $tokens = 1, int $maxWaitMs = 30000): bool
            {
                return $this->allowed;
            }

            public function getWaitTime(string $key): int { return 0; }

            public function reset(string $key): void {}

            /** @return array<string, mixed> */
            public function getStatus(string $key): array { return []; }

            /** @param array<string, mixed> $headers */
            public function updateFromHeaders(string $key, array $headers): void {}

            public function resetAll(): void {}

            /** @return array<string, mixed> */
            public function getAllStatuses(): array { return []; }

            public function cleanup(int $maxAgeSeconds = 3600): int { return 0; }

            public function getTypedStatus(string $key): \Four\RateLimit\RateLimitStatus
            {
                throw new \RuntimeException('Not implemented in mock');
            }

            /** @return array<string, \Four\RateLimit\RateLimitStatus> */
            public function getAllTypedStatuses(): array { return []; }
        };
    }

    public function testGetName(): void
    {
        $this->assertSame('rate_limiting', $this->middleware->getName());
    }

    public function testGetPriority(): void
    {
        $this->assertSame(200, $this->middleware->getPriority());
    }

    public function testWrapTransport(): void
    {
        $mockTransport = $this->createMockTransport([
            $this->createJsonResponse(['success' => true]),
        ]);

        $wrappedTransport = $this->middleware->wrap($mockTransport);

        $this->assertNotSame($mockTransport, $wrappedTransport);
        $this->assertInstanceOf(HttpTransportInterface::class, $wrappedTransport);
    }

    public function testSuccessfulRequestWithinLimits(): void
    {
        $mockTransport = $this->createMockTransport([
            $this->createRateLimitResponse('general', 100, 99),
        ]);

        $wrappedTransport = $this->middleware->wrap($mockTransport);

        $response = $wrappedTransport->request('GET', 'https://api.example.com/test');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRateLimitExceededResponseHandling(): void
    {
        $mockTransport = $this->createMockTransport([
            $this->createRateExceededResponse('general'),
        ]);

        $wrappedTransport = $this->middleware->wrap($mockTransport);

        $response = $wrappedTransport->request('GET', 'https://api.example.com/test');

        // RateLimitingTransport wartet und macht den Request trotzdem — 429 kommt zurück
        $this->assertSame(429, $response->getStatusCode());
    }

    public function testAmazonRateLimitHeaders(): void
    {
        $mockTransport = $this->createMockTransport([
            $this->createRateLimitResponse('amazon', 10, 8),
        ]);

        $amazonMiddleware = new RateLimitingMiddleware(
            $this->rateLimiter,
            'amazon',
            $this->logger,
        );

        $wrappedTransport = $amazonMiddleware->wrap($mockTransport);

        $response = $wrappedTransport->request('GET', 'https://sellingpartnerapi-eu.amazon.com/orders/v0/orders');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEbayRateLimitHeaders(): void
    {
        $mockTransport = $this->createMockTransport([
            $this->createRateLimitResponse('ebay', 5000, 4950),
        ]);

        $ebayMiddleware = new RateLimitingMiddleware(
            $this->rateLimiter,
            'ebay',
            $this->logger,
        );

        $wrappedTransport = $ebayMiddleware->wrap($mockTransport);

        $response = $wrappedTransport->request('GET', 'https://api.ebay.com/sell/inventory/v1/inventory_item');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDiscogsRateLimitHeaders(): void
    {
        $mockTransport = $this->createMockTransport([
            $this->createRateLimitResponse('discogs', 60, 58),
        ]);

        $discogsMiddleware = new RateLimitingMiddleware(
            $this->rateLimiter,
            'discogs',
            $this->logger,
        );

        $wrappedTransport = $discogsMiddleware->wrap($mockTransport);

        $response = $wrappedTransport->request('GET', 'https://api.discogs.com/database/search');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testWithOptionsPreservation(): void
    {
        $mockTransport = $this->createMockTransport([
            $this->createJsonResponse(['success' => true]),
        ]);

        $wrappedTransport = $this->middleware->wrap($mockTransport);

        $transportWithOptions = $wrappedTransport->withOptions([
            'timeout' => 60,
            'headers' => ['X-Test' => 'value'],
        ]);

        $this->assertNotSame($wrappedTransport, $transportWithOptions);

        $response = $transportWithOptions->request('GET', 'https://api.example.com/test');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMultipleRequestsAreAllAllowed(): void
    {
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->createJsonResponse(['request' => $i]);
        }

        $mockTransport = $this->createMockTransport($responses);
        $wrappedTransport = $this->middleware->wrap($mockTransport);

        for ($i = 0; $i < 5; $i++) {
            $response = $wrappedTransport->request('GET', "https://api.example.com/test{$i}");
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function testUpdateFromHeadersIsCalledAfterRequest(): void
    {
        // RateLimiter der updateFromHeaders-Aufrufe trackt
        $rateLimiter = new class implements RateLimiterInterface {
            public int $updateCount = 0;

            public function isAllowed(string $key, int $tokens = 1): bool { return true; }

            public function waitForAllowed(string $key, int $tokens = 1, int $maxWaitMs = 30000): bool
            {
                return true;
            }

            public function getWaitTime(string $key): int { return 0; }

            public function reset(string $key): void {}

            /** @return array<string, mixed> */
            public function getStatus(string $key): array { return []; }

            /** @param array<string, mixed> $headers */
            public function updateFromHeaders(string $key, array $headers): void
            {
                $this->updateCount++;
            }

            public function resetAll(): void {}

            /** @return array<string, mixed> */
            public function getAllStatuses(): array { return []; }

            public function cleanup(int $maxAgeSeconds = 3600): int { return 0; }

            public function getTypedStatus(string $key): \Four\RateLimit\RateLimitStatus
            {
                throw new \RuntimeException('Not implemented in mock');
            }

            /** @return array<string, \Four\RateLimit\RateLimitStatus> */
            public function getAllTypedStatuses(): array { return []; }
        };

        $mockTransport = $this->createMockTransport([
            $this->createJsonResponse(['success' => true]),
        ]);

        $middleware = new RateLimitingMiddleware($rateLimiter, 'general', $this->logger);
        $wrappedTransport = $middleware->wrap($mockTransport);

        $wrappedTransport->request('GET', 'https://api.example.com/test');

        $this->assertSame(1, $rateLimiter->updateCount);
    }
}
