<?php

declare(strict_types=1);

namespace Four\Http\Factory;

use Four\Http\Configuration\ClientConfig;
use Psr\Http\Client\ClientInterface;

/**
 * Factory interface for PSR-18 HTTP clients.
 *
 * Returns a PSR-18 ClientInterface, no framework-specific types.
 */
interface HttpClientFactoryInterface
{
    /**
     * Creates a PSR-18 compliant HTTP client with the given configuration.
     */
    public function create(ClientConfig $config): ClientInterface;

    /**
     * Returns the available middleware types.
     *
     * @return string[]
     */
    public function getAvailableMiddleware(): array;
}
