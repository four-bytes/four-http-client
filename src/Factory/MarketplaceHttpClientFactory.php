<?php

declare(strict_types=1);

namespace Four\Http\Factory;

use Four\Http\Configuration\ClientConfig;
use Four\Http\Middleware\LoggingMiddleware;
use Four\Http\Middleware\MiddlewareInterface;
use Four\Http\Middleware\RateLimitingMiddleware;
use Four\Http\Middleware\RetryMiddleware;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Factory für PSR-18-konforme HTTP-Clients.
 *
 * Kapselt Symfony HttpClient als PSR-18-Adapter.
 * Keine marketplace-spezifische Logik — generische Infrastruktur.
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
        // Symfony HttpClient als PSR-18-Adapter wrappen
        $symfonyClient = HttpClient::create($config->toHttpClientOptions());
        $psr18Client = new Psr18Client($symfonyClient);

        // Middleware auf Symfony-Ebene anwenden (Decorator-Pattern)
        $middleware = $this->getMiddlewareForConfig($config);

        // Nach Priority sortieren (absteigend)
        uasort(
            $middleware,
            static fn(MiddlewareInterface $a, MiddlewareInterface $b): int => $b->getPriority() <=> $a->getPriority(),
        );

        $decoratedSymfonyClient = $symfonyClient;
        foreach ($middleware as $middlewareInstance) {
            $decoratedSymfonyClient = $middlewareInstance->wrap($decoratedSymfonyClient);
        }

        return new Psr18Client($decoratedSymfonyClient);
    }

    public function getAvailableMiddleware(): array
    {
        return array_keys($this->availableMiddleware);
    }

    /**
     * Erstellt eine RateLimiterFactory für die gegebene Konfiguration.
     *
     * @param array<string, mixed> $config Optionale Überschreibung der Rate-Limit-Parameter
     */
    public function createRateLimiterFactory(string $id, array $config = []): RateLimiterFactory
    {
        $cache = $this->cache ?? new ArrayAdapter();
        $storage = new CacheStorage($cache);

        $defaults = [
            'id'     => $id,
            'policy' => 'token_bucket',
            'limit'  => 60,
            'rate'   => ['interval' => '1 minute', 'amount' => 60],
        ];

        return new RateLimiterFactory(array_merge($defaults, $config), $storage);
    }

    private function initializeMiddleware(): void
    {
        $this->availableMiddleware = [
            'logging'      => 'logging',
            'rate_limiting' => 'rate_limiting',
            'retry'        => 'retry',
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
                    if ($config->rateLimiterFactory !== null) {
                        $middleware[$name] = new RateLimitingMiddleware(
                            $config->rateLimiterFactory,
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
