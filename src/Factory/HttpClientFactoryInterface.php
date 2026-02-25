<?php

declare(strict_types=1);

namespace Four\Http\Factory;

use Four\Http\Configuration\ClientConfig;
use Psr\Http\Client\ClientInterface;

/**
 * Factory-Interface für PSR-18 HTTP-Clients.
 *
 * Gibt einen PSR-18 ClientInterface zurück, keine Framework-spezifischen Typen.
 */
interface HttpClientFactoryInterface
{
    /**
     * Erstellt einen PSR-18-konformen HTTP-Client mit der gegebenen Konfiguration.
     */
    public function create(ClientConfig $config): ClientInterface;

    /**
     * Gibt die verfügbaren Middleware-Typen zurück.
     *
     * @return string[]
     */
    public function getAvailableMiddleware(): array;
}
