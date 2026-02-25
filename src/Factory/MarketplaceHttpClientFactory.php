<?php

declare(strict_types=1);

namespace Four\Http\Factory;

use Four\Http\Configuration\ClientConfig;
use Four\Http\Middleware\LoggingMiddleware;
use Four\Http\Middleware\MiddlewareInterface;
use Four\Http\Middleware\RateLimitingMiddleware;
use Four\Http\Middleware\RetryMiddleware;
use Four\Http\Transport\HttpTransportInterface;
use Four\Http\Transport\SymfonyHttpTransport;
use Four\Http\Transport\TransportPsr18Adapter;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory für PSR-18-konforme HTTP-Clients.
 *
 * Baut intern über HttpTransportInterface + Middleware-Stack.
 * Symfony HttpClient wird als optionaler Default-Transport genutzt.
 */
class MarketplaceHttpClientFactory implements HttpClientFactoryInterface
{
    /** @var array<string, string> */
    private array $availableMiddleware = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
        $this->initializeMiddleware();
    }

    /**
     * Erstellt einen PSR-18-Client mit Middleware-Stack aus der Config.
     */
    public function create(ClientConfig $config): ClientInterface
    {
        // Transport aufbauen (Symfony als Default wenn verfügbar)
        $transport = $this->buildTransport($config);

        // Middleware stapeln
        $middleware = $this->getMiddlewareForConfig($config);

        // Nach Priority sortieren (absteigend)
        uasort(
            $middleware,
            static fn(MiddlewareInterface $a, MiddlewareInterface $b): int => $b->getPriority() <=> $a->getPriority(),
        );

        foreach ($middleware as $middlewareInstance) {
            $transport = $middlewareInstance->wrap($transport);
        }

        return new TransportPsr18Adapter($transport);
    }

    public function getAvailableMiddleware(): array
    {
        return array_keys($this->availableMiddleware);
    }

    /**
     * Baut den HTTP-Transport auf. Symfony wird als Default genutzt wenn installiert.
     */
    private function buildTransport(ClientConfig $config): HttpTransportInterface
    {
        if (class_exists(\Symfony\Component\HttpClient\HttpClient::class)) {
            $symfonyClient = \Symfony\Component\HttpClient\HttpClient::create($config->toHttpClientOptions());
            return new SymfonyHttpTransport($symfonyClient);
        }

        throw new \RuntimeException(
            'No HTTP transport available. Install symfony/http-client or provide a custom HttpTransportInterface.'
        );
    }

    private function initializeMiddleware(): void
    {
        $this->availableMiddleware = [
            'logging'       => 'logging',
            'rate_limiting' => 'rate_limiting',
            'retry'         => 'retry',
        ];
    }

    /**
     * Instanziiert die in der Config aktivierten Middleware-Objekte.
     *
     * @return array<string, MiddlewareInterface>
     */
    private function getMiddlewareForConfig(ClientConfig $config): array
    {
        $middleware = [];
        $logger = $config->logger ?? $this->logger ?? new NullLogger();

        foreach ($config->middleware as $name) {
            switch ($name) {
                case 'logging':
                    $middleware[$name] = new LoggingMiddleware($logger);
                    break;

                case 'rate_limiting':
                    if ($config->rateLimiter !== null) {
                        $middleware[$name] = new RateLimitingMiddleware(
                            $config->rateLimiter,
                            'general',
                            $logger,
                        );
                    }
                    break;

                case 'retry':
                    if ($config->retryConfig !== null) {
                        $middleware[$name] = new RetryMiddleware(
                            $config->retryConfig,
                            $logger,
                        );
                    }
                    break;
            }
        }

        return $middleware;
    }
}
