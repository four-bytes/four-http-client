<?php

declare(strict_types=1);

namespace Four\Http\Exception;

/**
 * Exception thrown when rate limits are exceeded.
 *
 * This exception is thrown when an HTTP request cannot be completed
 * due to rate limiting constraints, either from local rate limiting
 * or API rate limit responses.
 */
class RateLimitException extends HttpClientException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        int $code = 429,
        ?\Throwable $previous = null,
        ?string $operation = null,
        private readonly ?int $retryAfter = null,
        private readonly ?int $remainingRequests = null,
        private readonly ?int $requestLimit = null
    ) {
        parent::__construct($message, $code, $previous, $operation);
    }

    /**
     * Get the number of seconds to wait before retrying
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get the number of remaining requests allowed
     */
    public function getRemainingRequests(): ?int
    {
        return $this->remainingRequests;
    }

    /**
     * Get the total request limit
     */
    public function getRequestLimit(): ?int
    {
        return $this->requestLimit;
    }

    /** Default header names for the remaining request count (first match wins). */
    public const DEFAULT_REMAINING_HEADERS = ['x-ratelimit-remaining', 'ratelimit-remaining'];

    /** Default header names for the request limit (first match wins). */
    public const DEFAULT_LIMIT_HEADERS = ['x-ratelimit-limit', 'ratelimit-limit'];

    /**
     * Create exception from API response headers.
     *
     * `Retry-After` and `X-RateLimit-Reset` are read always; the header names for
     * remaining requests and the limit are configurable, so API clients for
     * platforms with own header names pass them in.
     *
     * @param array<string, array<string>|string> $headers
     * @param list<string> $remainingHeaders header names for remaining requests, case-insensitive
     * @param list<string> $limitHeaders     header names for the request limit, case-insensitive
     */
    public static function fromHeaders(
        array $headers,
        ?string $operation = null,
        array $remainingHeaders = self::DEFAULT_REMAINING_HEADERS,
        array $limitHeaders = self::DEFAULT_LIMIT_HEADERS,
    ): self {
        // Normalize header names to lowercase
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = is_array($value) ? $value[0] : $value;
        }

        $retryAfter = null;

        // Extract retry-after from various header formats
        if (isset($normalizedHeaders['retry-after'])) {
            $retryAfter = (int) $normalizedHeaders['retry-after'];
        } elseif (isset($normalizedHeaders['x-ratelimit-reset'])) {
            $resetTime = (int) $normalizedHeaders['x-ratelimit-reset'];
            $retryAfter = max(0, $resetTime - time());
        }

        $remaining = self::firstIntHeader($normalizedHeaders, $remainingHeaders);
        $limit = self::firstIntHeader($normalizedHeaders, $limitHeaders);

        $message = sprintf(
            'Rate limit exceeded%s%s%s',
            $remaining !== null ? " (remaining: {$remaining})" : '',
            $limit !== null ? " (limit: {$limit})" : '',
            $retryAfter !== null ? " (retry after: {$retryAfter}s)" : ''
        );

        return new self(
            $message,
            429,
            null,
            $operation,
            $retryAfter,
            $remaining,
            $limit
        );
    }

    /**
     * @param array<string, string> $normalizedHeaders lowercase names
     * @param list<string> $names
     */
    private static function firstIntHeader(array $normalizedHeaders, array $names): ?int
    {
        foreach ($names as $name) {
            $key = strtolower($name);
            if (isset($normalizedHeaders[$key])) {
                return (int) $normalizedHeaders[$key];
            }
        }

        return null;
    }
}
