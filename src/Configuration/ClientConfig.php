<?php

declare(strict_types=1);

namespace Four\Http\Configuration;

use Four\Http\Authentication\AuthProviderInterface;
use Four\RateLimit\RateLimiterInterface;
use Psr\Log\LoggerInterface;

/**
 * Configuration class for HTTP client creation.
 *
 * This class holds all configuration options for creating HTTP clients,
 * including base URLs, authentication, middleware options, and performance settings.
 */
readonly class ClientConfig
{
    /**
     * @param string $baseUri Base URI for API requests
     * @param array<string, mixed> $defaultHeaders Default headers to include with requests
     * @param array<string> $middleware List of middleware to apply
     * @param AuthProviderInterface|null $authProvider Authentication provider
     * @param RateLimiterInterface|null $rateLimiter Rate limiter instance
     * @param LoggerInterface|null $logger Logger instance
     * @param RetryConfig|null $retryConfig Retry configuration
     * @param float $timeout Request timeout in seconds
     * @param int $maxRedirects Maximum number of redirects to follow
     * @param array<string, mixed> $additionalOptions Additional client options
     */
    public function __construct(
        public string $baseUri,
        public array $defaultHeaders = [],
        public array $middleware = [],
        public ?AuthProviderInterface $authProvider = null,
        public ?RateLimiterInterface $rateLimiter = null,
        public ?LoggerInterface $logger = null,
        public ?RetryConfig $retryConfig = null,
        public float $timeout = 30.0,
        public int $maxRedirects = 3,
        public array $additionalOptions = []
    ) {}

    /**
     * Create a new configuration builder
     */
    public static function create(string $baseUri): ClientConfigBuilder
    {
        return new ClientConfigBuilder($baseUri);
    }

    /**
     * Create configuration with modified properties
     *
     * @param array<string, mixed>|null $defaultHeaders
     * @param array<string>|null $middleware
     * @param array<string, mixed>|null $additionalOptions
     */
    public function with(
        ?string $baseUri = null,
        ?array $defaultHeaders = null,
        ?array $middleware = null,
        ?AuthProviderInterface $authProvider = null,
        ?RateLimiterInterface $rateLimiter = null,
        ?LoggerInterface $logger = null,
        ?RetryConfig $retryConfig = null,
        ?float $timeout = null,
        ?int $maxRedirects = null,
        ?array $additionalOptions = null
    ): self {
        return new self(
            $baseUri ?? $this->baseUri,
            $defaultHeaders ?? $this->defaultHeaders,
            $middleware ?? $this->middleware,
            $authProvider ?? $this->authProvider,
            $rateLimiter ?? $this->rateLimiter,
            $logger ?? $this->logger,
            $retryConfig ?? $this->retryConfig,
            $timeout ?? $this->timeout,
            $maxRedirects ?? $this->maxRedirects,
            $additionalOptions ?? $this->additionalOptions
        );
    }

    /**
     * Check if specific middleware is enabled
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    /**
     * Get merged headers including authentication headers
     *
     * @return array<string, mixed>
     */
    public function getMergedHeaders(): array
    {
        $headers = $this->defaultHeaders;

        if ($this->authProvider !== null) {
            $authHeaders = $this->authProvider->getAuthHeaders();
            $headers = array_merge($headers, $authHeaders);
        }

        return $headers;
    }

    /**
     * Convert configuration to HTTP client options array.
     *
     * @return array<string, mixed>
     */
    public function toHttpClientOptions(): array
    {
        $options = [
            'headers' => $this->getMergedHeaders(),
            'timeout' => $this->timeout,
            'max_redirects' => $this->maxRedirects,
        ];

        return array_merge($options, $this->additionalOptions);
    }
}
