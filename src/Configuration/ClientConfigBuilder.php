<?php

declare(strict_types=1);

namespace Four\Http\Configuration;

use Four\Http\Authentication\AuthProviderInterface;
use Four\RateLimit\RateLimiterInterface;
use Psr\Log\LoggerInterface;

/**
 * Builder class for ClientConfig
 *
 * Provides a fluent interface for constructing ClientConfig instances
 * with validation and sensible defaults.
 */
class ClientConfigBuilder
{
    private string $baseUri;
    
    /** @var array<string, mixed> */
    private array $defaultHeaders = [];
    
    /** @var array<string> */
    private array $middleware = [];
    
    private ?AuthProviderInterface $authProvider = null;
    private ?RateLimiterInterface $rateLimiter = null;
    private ?LoggerInterface $logger = null;
    private ?RetryConfig $retryConfig = null;
    private float $timeout = 30.0;
    private int $maxRedirects = 3;
    
    /** @var array<string, mixed> */
    private array $additionalOptions = [];

    public function __construct(string $baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * Set default headers for all requests
     *
     * @param array<string, mixed> $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Add a single header
     */
    public function withHeader(string $name, string $value): self
    {
        $this->defaultHeaders[$name] = $value;
        return $this;
    }

    /**
     * Set User-Agent header
     */
    public function withUserAgent(string $userAgent): self
    {
        return $this->withHeader('User-Agent', $userAgent);
    }

    /**
     * Set Accept header
     */
    public function withAccept(string $accept): self
    {
        return $this->withHeader('Accept', $accept);
    }

    /**
     * Set Content-Type header
     */
    public function withContentType(string $contentType): self
    {
        return $this->withHeader('Content-Type', $contentType);
    }

    /**
     * Enable specific middleware
     *
     * @param string|array<string> $middleware
     */
    public function withMiddleware(string|array $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }
        
        foreach ($middleware as $name) {
            if (!in_array($name, $this->middleware, true)) {
                $this->middleware[] = $name;
            }
        }
        
        return $this;
    }

    /**
     * Enable logging middleware
     */
    public function withLogging(?LoggerInterface $logger = null): self
    {
        $this->logger = $logger;
        return $this->withMiddleware('logging');
    }

    /**
     * Enable rate limiting middleware
     */
    public function withRateLimit(?RateLimiterInterface $rateLimiter = null): self
    {
        $this->rateLimiter = $rateLimiter;
        return $this->withMiddleware('rate_limiting');
    }

    /**
     * Enable retry middleware
     */
    public function withRetries(?RetryConfig $retryConfig = null): self
    {
        $this->retryConfig = $retryConfig ?? RetryConfig::default();
        return $this->withMiddleware('retry');
    }

    /**
     * Enable authentication middleware
     */
    public function withAuthentication(AuthProviderInterface $authProvider): self
    {
        $this->authProvider = $authProvider;
        return $this->withMiddleware('authentication');
    }

    /**
     * Set request timeout
     */
    public function withTimeout(float $timeout): self
    {
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than 0');
        }
        
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set maximum redirects
     */
    public function withMaxRedirects(int $maxRedirects): self
    {
        if ($maxRedirects < 0) {
            throw new \InvalidArgumentException('Max redirects must be non-negative');
        }
        
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    /**
     * Add additional options for the HTTP client
     *
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): self
    {
        $this->additionalOptions = array_merge($this->additionalOptions, $options);
        return $this;
    }

    /**
     * Configure authentication using simple parameters
     *
     * @param array<string, mixed> $options
     */
    public function withAuth(string $type, string $credentials, array $options = []): self
    {
        match ($type) {
            'bearer' => $this->withHeader('Authorization', "Bearer {$credentials}"),
            'basic' => $this->withHeader('Authorization', 'Basic ' . base64_encode($credentials)),
            'api_key' => $this->withHeader('Authorization', $credentials),
            'token' => $this->withHeader('Authorization', "Token {$credentials}"),
            default => throw new \InvalidArgumentException("Unsupported auth type: {$type}")
        };
        
        return $this;
    }

    /**
     * Configure retry logic with simple parameters
     *
     * @param array<int> $retryableStatusCodes
     */
    public function withRetryPolicy(int $maxAttempts, array $retryableStatusCodes = [429, 500, 502, 503, 504]): self
    {
        $retryConfig = new RetryConfig(
            maxAttempts: $maxAttempts,
            retryableStatusCodes: $retryableStatusCodes,
        );

        $this->retryConfig = $retryConfig;
        return $this->withMiddleware('retry');
    }

    /**
     * Build the final ClientConfig instance
     */
    public function build(): ClientConfig
    {
        return new ClientConfig(
            $this->baseUri,
            $this->defaultHeaders,
            $this->middleware,
            $this->authProvider,
            $this->rateLimiter,
            $this->logger,
            null,
            $this->retryConfig,
            $this->timeout,
            $this->maxRedirects,
            $this->additionalOptions
        );
    }
}