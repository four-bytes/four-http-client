<?php

declare(strict_types=1);

namespace Four\Http\Middleware;

use Four\Http\Transport\HttpResponseInterface;
use Four\Http\Transport\HttpTransportInterface;
use Four\RateLimit\RateLimiterInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Middleware that applies rate limiting to HTTP requests
 *
 * Uses four-bytes/four-rate-limiting to control request frequency
 * and prevent API rate limit violations.
 */
class RateLimitingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterInterface $rateLimiter,
        private readonly string $key = 'general',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function wrap(HttpTransportInterface $transport): HttpTransportInterface
    {
        return new RateLimitingTransport($transport, $this->rateLimiter, $this->key, $this->logger);
    }

    public function getName(): string
    {
        return 'rate_limiting';
    }

    public function getPriority(): int
    {
        return 200; // Apply rate limiting early, before actual requests
    }
}

/**
 * HTTP Transport decorator that applies rate limiting
 */
class RateLimitingTransport implements HttpTransportInterface
{
    public function __construct(
        private readonly HttpTransportInterface $transport,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly string $key,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $this->rateLimiter->waitForAllowed($this->key);

        $response = $this->transport->request($method, $url, $options);

        // Header-basiertes dynamisches Tracking
        try {
            $headers = $response->getHeaders(false);
            $this->rateLimiter->updateFromHeaders($this->key, $headers);
        } catch (\Exception $e) {
            $this->logger->debug('Rate limit header update failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        // @phpstan-ignore new.static
        return new static($this->transport->withOptions($options), $this->rateLimiter, $this->key, $this->logger);
    }
}
