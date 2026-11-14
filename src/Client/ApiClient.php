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
 * Abstract base class for API clients.
 *
 * Infrastruktur-Layer: PSR-18, Auth-Injection, Error-Mapping.
 * No API-specific logic.
 */
abstract class ApiClient
{
    public function __construct(
        protected readonly ClientConfig $config,
        protected readonly ClientInterface $httpClient,
        protected readonly RequestFactoryInterface $requestFactory,
        protected readonly StreamFactoryInterface $streamFactory,
        protected readonly ?AuthProviderInterface $auth = null,
    ) {}

    /**
     * GET request — returns the decoded JSON body.
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

        return $this->handleResponse($response, $request);
    }

    /**
     * POST request — returns the decoded JSON body.
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

        return $this->handleResponse($response, $request);
    }

    /**
     * PATCH request — returns the decoded JSON body.
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

        return $this->handleResponse($response, $request);
    }

    /**
     * DELETE request — no return value.
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
     * Builds a PSR-7 request with auth headers and default headers.
     */
    protected function buildRequest(string $method, string $url, ?string $body = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        // Default headers from config
        foreach ($this->config->defaultHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Inject auth headers
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
     * Processes a PSR-7 response and throws typed exceptions on errors.
     *
     * @return array<string, mixed>
     * @throws AuthenticationException on 401
     * @throws NotFoundException       on 404
     * @throws RateLimitException      on 429
     * @throws RetryableException      on 5xx
     * @throws HttpClientException     on other 4xx
     */
    protected function handleResponse(ResponseInterface $response, ?RequestInterface $request = null): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            $body = (string) $response->getBody();
            if ($body === '' || $body === 'null') {
                return [];
            }

            try {
                return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $ctype = $response->getHeaderLine('Content-Type') ?: '(none)';
                $len = strlen($body);
                $preview = trim(preg_replace('/\s+/', ' ', substr($body, 0, 200)));

                // Dump full body + request URL to disk for inspection
                $dumpPath = null;
                $rand = bin2hex(random_bytes(4));
                $dumpPath = "/tmp/four-http-jsonerr-" . date('YmdHis') . "-{$rand}.txt";
                $urlLine = $request !== null
                    ? "URL: {$request->getMethod()} {$request->getUri()}"
                    : "URL: (request not provided)";
                $dumpContent = "{$urlLine}\nStatus: {$statusCode}\nContent-Type: {$ctype}\nBody-Length: {$len}\n---BODY---\n{$body}";
                @file_put_contents($dumpPath, $dumpContent);

                throw new HttpClientException(
                    message: "JSON decode failed: {$e->getMessage()} (HTTP {$statusCode}, Content-Type: {$ctype}, body {$len} bytes): {$preview} [dump: {$dumpPath}]",
                    code: $statusCode,
                    previous: $e,
                );
            }
        }

        $this->throwForStatus($statusCode, $response);
    }

    /**
     * Throws a typed exception based on the HTTP status.
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
                null,
                $this->rateLimitRemainingHeaders(),
                $this->rateLimitLimitHeaders(),
            ),
            $statusCode >= 500  => throw RetryableException::serverError($statusCode),
            default             => throw new HttpClientException(
                message: "HTTP error {$statusCode}" . ($body !== '' ? " — {$body}" : ''),
                code: $statusCode,
            ),
        };
    }

    /**
     * Header names carrying the remaining request count on a 429 response.
     * Override in an API client whose platform uses own header names.
     *
     * @return list<string>
     */
    protected function rateLimitRemainingHeaders(): array
    {
        return RateLimitException::DEFAULT_REMAINING_HEADERS;
    }

    /**
     * Header names carrying the request limit on a 429 response.
     * Override in an API client whose platform uses own header names.
     *
     * @return list<string>
     */
    protected function rateLimitLimitHeaders(): array
    {
        return RateLimitException::DEFAULT_LIMIT_HEADERS;
    }

    /**
     * Normalizes PSR-7 header arrays for RateLimitException::fromHeaders().
     *
     * @param array<string, string|string[]> $headers
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
