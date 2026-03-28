<?php

declare(strict_types=1);

namespace Four\Http\Authentication;

/**
 * Interface for per-request signing.
 *
 * Unlike AuthProviderInterface (which provides static auth headers),
 * RequestSignerInterface computes signatures based on the full request context
 * (method, URL, headers, body). Used for OAuth 1.0a, HMAC-SHA256, AWS SigV4, etc.
 *
 * Implementations can modify both the URL (e.g., add query params like `sign`)
 * and headers (e.g., add Authorization header).
 */
interface RequestSignerInterface
{
    /**
     * Sign a request based on its full context.
     *
     * Returns modified URL and additional headers. The middleware will:
     * 1. Replace the original URL with the returned URL
     * 2. Merge the returned headers with existing request headers
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Full request URL (including existing query params)
     * @param array<string, string> $headers Current request headers
     * @param string $body Request body (empty string for GET/DELETE)
     * @return array{url: string, headers: array<string, string>}
     */
    public function signRequest(string $method, string $url, array $headers, string $body): array;

    /**
     * Get the name of this signer (for logging/debugging).
     */
    public function getName(): string;
}
