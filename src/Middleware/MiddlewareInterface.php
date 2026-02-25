<?php

declare(strict_types=1);

namespace Four\Http\Middleware;

use Four\Http\Transport\HttpTransportInterface;

/**
 * Interface for HTTP client middleware
 *
 * Middleware components can wrap HttpTransport instances to add functionality
 * like logging, rate limiting, caching, authentication, and monitoring.
 */
interface MiddlewareInterface
{
    /**
     * Wrap an HTTP transport with middleware functionality
     */
    public function wrap(HttpTransportInterface $transport): HttpTransportInterface;

    /**
     * Get the name of this middleware
     */
    public function getName(): string;

    /**
     * Get the priority of this middleware (higher numbers are applied first)
     */
    public function getPriority(): int;
}