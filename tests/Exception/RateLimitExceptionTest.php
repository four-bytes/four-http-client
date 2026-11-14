<?php

declare(strict_types=1);

namespace Four\Http\Tests\Exception;

use Four\Http\Exception\RateLimitException;
use PHPUnit\Framework\TestCase;

final class RateLimitExceptionTest extends TestCase
{
    public function testReadsDefaultHeaders(): void
    {
        $e = RateLimitException::fromHeaders([
            'Retry-After' => '30',
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Limit' => '100',
        ]);

        self::assertSame(30, $e->getRetryAfter());
        self::assertSame(0, $e->getRemainingRequests());
        self::assertSame(100, $e->getRequestLimit());
    }

    public function testReadsIetfRateLimitHeaders(): void
    {
        $e = RateLimitException::fromHeaders(['RateLimit-Remaining' => '3', 'RateLimit-Limit' => '10']);

        self::assertSame(3, $e->getRemainingRequests());
        self::assertSame(10, $e->getRequestLimit());
    }

    public function testPlatformHeadersAreIgnoredByDefault(): void
    {
        $e = RateLimitException::fromHeaders(['X-Discogs-Ratelimit-Remaining' => '0']);

        self::assertNull($e->getRemainingRequests());
    }

    public function testCustomHeaderNames(): void
    {
        $e = RateLimitException::fromHeaders(
            ['X-Discogs-Ratelimit-Remaining' => ['5'], 'X-Discogs-Ratelimit' => '60'],
            'search',
            ['x-discogs-ratelimit-remaining'],
            ['X-Discogs-Ratelimit'],
        );

        self::assertSame(5, $e->getRemainingRequests());
        self::assertSame(60, $e->getRequestLimit());
        self::assertSame('search', $e->getOperation());
    }
}
