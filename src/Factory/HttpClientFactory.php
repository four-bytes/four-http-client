<?php

declare(strict_types=1);

namespace Four\Http\Factory;

use Four\Http\Configuration\ClientConfig;
use Four\Http\Middleware\LoggingMiddleware;
use Four\Http\Middleware\MiddlewareInterface;
use Four\Http\Middleware\OAuth1aMiddleware;
use Four\Http\Middleware\RateLimitingMiddleware;
use Four\Http\Middleware\RetryMiddleware;
use Four\Http\Transport\DiscoveryHttpTransport;
use Four\Http\Transport\HttpTransportInterface;
use Four\Http\Transport\TransportPsr18Adapter;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory für PSR-18-konforme HTTP-Clients.
 *
 * Baut intern über HttpTransportInterface + Middleware-Stack.
 * Nutzt php-http/discovery zur automatischen PSR-18 Client-Erkennung.
 */
class HttpClientFactory implements HttpClientFactoryInterface
{
    /** @var array<string, string> */
    private array $availableMiddleware = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->initializeMiddleware();
    }

    /**
     * Erstellt einen PSR-18-Client mit Middleware-Stack aus der Config.
     */
    public function create(ClientConfig $config): ClientInterface
    {
        // Transport aufbauen (via php-http/discovery)
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
     * Baut den HTTP-Transport auf via php-http/discovery.
     */
    private function buildTransport(ClientConfig $config): HttpTransportInterface
    {
        try {
            $psrClient = \Http\Discovery\Psr18ClientDiscovery::find();
            return new DiscoveryHttpTransport($psrClient, $config->toHttpClientOptions());
        } catch (\Http\Discovery\Exception\NotFoundException $e) {
            throw new \RuntimeException(
                'No PSR-18 HTTP client found. Install symfony/http-client, guzzlehttp/guzzle, or another PSR-18 implementation.',
                0,
                $e
            );
        }
    }

    private function initializeMiddleware(): void
    {
        $this->availableMiddleware = [
            'logging'       => 'logging',
            'rate_limiting' => 'rate_limiting',
            'retry'         => 'retry',
            'oauth_1a'      => 'oauth_1a',
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

                case 'oauth_1a':
                    if ($config->authProvider instanceof \Four\Http\Authentication\OAuth1aProvider) {
                        $middleware[$name] = new OAuth1aMiddleware(
                            $config->authProvider,
                            $logger,
                        );
                    }
                    break;
            }
        }

        return $middleware;
    }
}
