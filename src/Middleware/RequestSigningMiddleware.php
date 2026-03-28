<?php

declare(strict_types=1);

namespace Four\Http\Middleware;

use Four\Http\Authentication\RequestSignerInterface;
use Four\Http\Transport\HttpResponseInterface;
use Four\Http\Transport\HttpTransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Generic middleware that applies per-request signing.
 *
 * Works with any RequestSignerInterface implementation:
 * - OAuth 1.0a (signature in Authorization header)
 * - TikTok HMAC-SHA256 (signature in query params)
 * - Amazon SigV4 (signature in headers)
 * - etc.
 */
class RequestSigningMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RequestSignerInterface $signer,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function wrap(HttpTransportInterface $transport): HttpTransportInterface
    {
        return new RequestSigningTransport($transport, $this->signer, $this->logger);
    }

    public function getName(): string
    {
        return 'request_signing';
    }

    public function getPriority(): int
    {
        return 300; // High priority — sign before rate limiting and logging
    }
}

/**
 * HTTP Transport decorator that applies request signing.
 */
class RequestSigningTransport implements HttpTransportInterface
{
    use SanitizesUrl;

    public function __construct(
        private readonly HttpTransportInterface $transport,
        private readonly RequestSignerInterface $signer,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        // Extract current headers and body from options
        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? '';

        // Let the signer modify URL and produce additional headers
        $signed = $this->signer->signRequest($method, $url, $headers, $body);

        // Apply signed URL
        $signedUrl = $signed['url'];

        // Merge signed headers with existing headers
        $options['headers'] = array_merge($headers, $signed['headers']);

        $this->logger->debug('Request signed', [
            'signer' => $this->signer->getName(),
            'method' => $method,
            'url' => $this->sanitizeUrl($signedUrl),
            'added_headers' => count($signed['headers']),
        ]);

        try {
            $response = $this->transport->request($method, $signedUrl, $options);

            $this->logger->debug('Signed request completed', [
                'signer' => $this->signer->getName(),
                'method' => $method,
                'url' => $this->sanitizeUrl($signedUrl),
                'status_code' => $response->getStatusCode(),
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Signed request failed', [
                'signer' => $this->signer->getName(),
                'method' => $method,
                'url' => $this->sanitizeUrl($signedUrl),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        // @phpstan-ignore new.static
        return new static(
            $this->transport->withOptions($options),
            $this->signer,
            $this->logger,
        );
    }
}
