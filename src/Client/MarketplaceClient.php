<?php

declare(strict_types=1);

namespace Four\Http\Client;

use Four\Http\Authentication\AuthProviderInterface;
use Four\Http\Configuration\ClientConfig;
use Four\Http\Exception\AuthenticationException;
use Four\Http\Exception\HttpClientException;
use Four\Http\Exception\NotFoundException;
use Four\Http\Exception\RateLimitException;
use Four\Http\Exception\RetryableException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Abstrakte Basis-Klasse für Marketplace-Clients.
 *
 * Infrastruktur-Layer: PSR-18, Auth-Injection, Error-Mapping.
 * Keine marketplace-spezifische Logik.
 */
abstract class MarketplaceClient
{
    public function __construct(
        protected readonly ClientConfig $config,
        protected readonly ClientInterface $httpClient,
        protected readonly RequestFactoryInterface $requestFactory,
        protected readonly StreamFactoryInterface $streamFactory,
        protected readonly ?AuthProviderInterface $auth = null,
    ) {}

    /**
     * GET-Request — gibt dekodierten JSON-Body zurück.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws HttpClientException
     */
    protected function httpGet(string $url, array $query = []): array
    {
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $request = $this->buildRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        return $this->handleResponse($response);
    }

    /**
     * POST-Request — gibt dekodierten JSON-Body zurück.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws HttpClientException
     */
    protected function httpPost(string $url, array $body = []): array
    {
        $encoded = !empty($body) ? json_encode($body, JSON_THROW_ON_ERROR) : null;
        $request = $this->buildRequest('POST', $url, $encoded);
        $response = $this->httpClient->sendRequest($request);

        return $this->handleResponse($response);
    }

    /**
     * PATCH-Request — gibt dekodierten JSON-Body zurück.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws HttpClientException
     */
    protected function httpPatch(string $url, array $body = []): array
    {
        $encoded = !empty($body) ? json_encode($body, JSON_THROW_ON_ERROR) : null;
        $request = $this->buildRequest('PATCH', $url, $encoded);
        $response = $this->httpClient->sendRequest($request);

        return $this->handleResponse($response);
    }

    /**
     * DELETE-Request — kein Rückgabewert.
     *
     * @throws HttpClientException
     */
    protected function httpDelete(string $url): void
    {
        $request = $this->buildRequest('DELETE', $url);
        $response = $this->httpClient->sendRequest($request);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatus($statusCode, $response);
        }
    }

    /**
     * Baut einen PSR-7-Request mit Auth-Headers und Default-Headers.
     */
    protected function buildRequest(string $method, string $url, ?string $body = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        // Default-Headers aus Config
        foreach ($this->config->defaultHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Auth-Headers injizieren
        if ($this->auth !== null) {
            foreach ($this->auth->getAuthHeaders() as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        // Body + Content-Type
        if ($body !== null) {
            $stream = $this->streamFactory->createStream($body);
            $request = $request
                ->withBody($stream)
                ->withHeader('Content-Type', 'application/json');
        }

        return $request;
    }

    /**
     * Verarbeitet PSR-7-Response und wirft typisierte Exceptions bei Fehlern.
     *
     * @return array<string, mixed>
     * @throws AuthenticationException bei 401
     * @throws NotFoundException       bei 404
     * @throws RateLimitException      bei 429
     * @throws RetryableException      bei 5xx
     * @throws HttpClientException     bei anderen 4xx
     */
    protected function handleResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            $body = (string) $response->getBody();
            if ($body === '' || $body === 'null') {
                return [];
            }

            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        }

        $this->throwForStatus($statusCode, $response);

        // Unreachable — throwForStatus wirft immer
        return [];
    }

    /**
     * Wirft eine typisierte Exception basierend auf HTTP-Status.
     *
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws RateLimitException
     * @throws RetryableException
     * @throws HttpClientException
     */
    private function throwForStatus(int $statusCode, ResponseInterface $response): never
    {
        $body = (string) $response->getBody();

        match (true) {
            $statusCode === 401 => throw new AuthenticationException(
                message: "Authentication failed: HTTP 401" . ($body !== '' ? " — {$body}" : ''),
                code: 401,
            ),
            $statusCode === 404 => throw new NotFoundException(
                message: "Resource not found: HTTP 404" . ($body !== '' ? " — {$body}" : ''),
                code: 404,
            ),
            $statusCode === 429 => throw RateLimitException::fromHeaders(
                $this->normalizeHeaders($response->getHeaders()),
            ),
            $statusCode >= 500  => throw RetryableException::serverError($statusCode),
            default             => throw new HttpClientException(
                message: "HTTP error {$statusCode}" . ($body !== '' ? " — {$body}" : ''),
                code: $statusCode,
            ),
        };
    }

    /**
     * Normalisiert PSR-7-Header-Arrays für RateLimitException::fromHeaders().
     *
     * @param array<string, string[]> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $values) {
            $normalized[$name] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        return $normalized;
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }
}
