<?php

declare(strict_types=1);

namespace Four\Http\Tests\Middleware;

use Four\Http\Authentication\RequestSignerInterface;
use Four\Http\Middleware\RequestSigningMiddleware;
use Four\Http\Middleware\RequestSigningTransport;
use Four\Http\Tests\TestCase;
use Four\Http\Transport\HttpTransportInterface;

/**
 * Tests for RequestSigningMiddleware and RequestSigningTransport
 */
class RequestSigningMiddlewareTest extends TestCase
{
    private RequestSignerInterface $signer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a simple test signer that appends a fixed header
        $this->signer = new class implements RequestSignerInterface {
            public function signRequest(string $method, string $url, array $headers, string $body): array
            {
                return [
                    'url' => $url,
                    'headers' => ['X-Signature' => 'test-sig-' . strtolower($method)],
                ];
            }

            public function getName(): string
            {
                return 'test_signer';
            }
        };
    }

    public function testMiddlewareGetName(): void
    {
        $middleware = new RequestSigningMiddleware($this->signer);

        $this->assertSame('request_signing', $middleware->getName());
    }

    public function testMiddlewareGetPriority(): void
    {
        $middleware = new RequestSigningMiddleware($this->signer);

        $this->assertSame(300, $middleware->getPriority());
    }

    public function testWrapReturnsRequestSigningTransport(): void
    {
        $middleware = new RequestSigningMiddleware($this->signer);
        $mockTransport = $this->createMockTransport([
            $this->createJsonResponse(['ok' => true]),
        ]);

        $wrapped = $middleware->wrap($mockTransport);

        $this->assertInstanceOf(RequestSigningTransport::class, $wrapped);
    }

    public function testSigningAddsHeadersToRequest(): void
    {
        $capturedOptions = null;

        $transport = new class($capturedOptions) implements HttpTransportInterface {
            public function __construct(private mixed &$captured) {}

            public function request(string $method, string $url, array $options = []): \Four\Http\Transport\HttpResponseInterface
            {
                $this->captured = $options;
                return new class implements \Four\Http\Transport\HttpResponseInterface {
                    public function getStatusCode(): int { return 200; }
                    public function getHeaders(bool $throw = true): array { return []; }
                    public function getContent(bool $throw = true): string { return '{}'; }
                    public function toArray(bool $throw = true): array { return []; }
                };
            }

            public function withOptions(array $options): static { return clone $this; }
        };

        $middleware = new RequestSigningMiddleware($this->signer);
        $wrapped = $middleware->wrap($transport);

        $wrapped->request('GET', 'https://api.example.com/test', []);

        $this->assertNotNull($capturedOptions);
        $this->assertArrayHasKey('headers', $capturedOptions);
        $this->assertArrayHasKey('X-Signature', $capturedOptions['headers']);
        $this->assertSame('test-sig-get', $capturedOptions['headers']['X-Signature']);
    }

    public function testSigningMergesWithExistingHeaders(): void
    {
        $capturedOptions = null;

        $transport = new class($capturedOptions) implements HttpTransportInterface {
            public function __construct(private mixed &$captured) {}

            public function request(string $method, string $url, array $options = []): \Four\Http\Transport\HttpResponseInterface
            {
                $this->captured = $options;
                return new class implements \Four\Http\Transport\HttpResponseInterface {
                    public function getStatusCode(): int { return 200; }
                    public function getHeaders(bool $throw = true): array { return []; }
                    public function getContent(bool $throw = true): string { return '{}'; }
                    public function toArray(bool $throw = true): array { return []; }
                };
            }

            public function withOptions(array $options): static { return clone $this; }
        };

        $middleware = new RequestSigningMiddleware($this->signer);
        $wrapped = $middleware->wrap($transport);

        $wrapped->request('POST', 'https://api.example.com/test', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertNotNull($capturedOptions);
        $this->assertArrayHasKey('Content-Type', $capturedOptions['headers']);
        $this->assertArrayHasKey('X-Signature', $capturedOptions['headers']);
        $this->assertSame('application/json', $capturedOptions['headers']['Content-Type']);
        $this->assertSame('test-sig-post', $capturedOptions['headers']['X-Signature']);
    }

    public function testSigningCanModifyUrl(): void
    {
        $capturedUrl = null;

        // Signer that adds a query param to the URL
        $urlModifyingSigner = new class implements RequestSignerInterface {
            public function signRequest(string $method, string $url, array $headers, string $body): array
            {
                return [
                    'url' => $url . '?sign=abc123',
                    'headers' => [],
                ];
            }

            public function getName(): string
            {
                return 'url_modifying_signer';
            }
        };

        $transport = new class($capturedUrl) implements HttpTransportInterface {
            public function __construct(private mixed &$captured) {}

            public function request(string $method, string $url, array $options = []): \Four\Http\Transport\HttpResponseInterface
            {
                $this->captured = $url;
                return new class implements \Four\Http\Transport\HttpResponseInterface {
                    public function getStatusCode(): int { return 200; }
                    public function getHeaders(bool $throw = true): array { return []; }
                    public function getContent(bool $throw = true): string { return '{}'; }
                    public function toArray(bool $throw = true): array { return []; }
                };
            }

            public function withOptions(array $options): static { return clone $this; }
        };

        $middleware = new RequestSigningMiddleware($urlModifyingSigner);
        $wrapped = $middleware->wrap($transport);

        $wrapped->request('GET', 'https://api.example.com/test', []);

        $this->assertSame('https://api.example.com/test?sign=abc123', $capturedUrl);
    }

    public function testSigningPassesBodyToSigner(): void
    {
        $capturedBody = null;

        $bodySigner = new class($capturedBody) implements RequestSignerInterface {
            public function __construct(private mixed &$captured) {}

            public function signRequest(string $method, string $url, array $headers, string $body): array
            {
                $this->captured = $body;
                return ['url' => $url, 'headers' => []];
            }

            public function getName(): string
            {
                return 'body_signer';
            }
        };

        $transport = $this->createMockTransport([
            $this->createJsonResponse(['ok' => true]),
        ]);

        $middleware = new RequestSigningMiddleware($bodySigner);
        $wrapped = $middleware->wrap($transport);

        $wrapped->request('POST', 'https://api.example.com/test', [
            'body' => '{"key":"value"}',
        ]);

        $this->assertSame('{"key":"value"}', $capturedBody);
    }

    public function testSigningPassesMethodToSigner(): void
    {
        $capturedMethod = null;

        $methodSigner = new class($capturedMethod) implements RequestSignerInterface {
            public function __construct(private mixed &$captured) {}

            public function signRequest(string $method, string $url, array $headers, string $body): array
            {
                $this->captured = $method;
                return ['url' => $url, 'headers' => []];
            }

            public function getName(): string
            {
                return 'method_signer';
            }
        };

        $transport = $this->createMockTransport([
            $this->createJsonResponse(['ok' => true]),
        ]);

        $middleware = new RequestSigningMiddleware($methodSigner);
        $wrapped = $middleware->wrap($transport);

        $wrapped->request('DELETE', 'https://api.example.com/resource/1', []);

        $this->assertSame('DELETE', $capturedMethod);
    }

    public function testExceptionIsPropagated(): void
    {
        $throwingTransport = new class implements HttpTransportInterface {
            public function request(string $method, string $url, array $options = []): \Four\Http\Transport\HttpResponseInterface
            {
                throw new \RuntimeException('Network error');
            }

            public function withOptions(array $options): static { return clone $this; }
        };

        $middleware = new RequestSigningMiddleware($this->signer);
        $wrapped = $middleware->wrap($throwingTransport);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network error');

        $wrapped->request('GET', 'https://api.example.com/test', []);
    }

    public function testWithOptionsReturnsSameSignerAndLogger(): void
    {
        $response = $this->createJsonResponse(['ok' => true]);
        $transport = $this->createMockTransport([$response, $response]);

        $middleware = new RequestSigningMiddleware($this->signer);
        $wrapped = $middleware->wrap($transport);

        $this->assertInstanceOf(RequestSigningTransport::class, $wrapped);

        $cloned = $wrapped->withOptions(['timeout' => 10]);
        $this->assertInstanceOf(RequestSigningTransport::class, $cloned);
    }

    public function testEmptyBodyDefaultsToEmptyString(): void
    {
        $capturedBody = null;

        $bodySigner = new class($capturedBody) implements RequestSignerInterface {
            public function __construct(private mixed &$captured) {}

            public function signRequest(string $method, string $url, array $headers, string $body): array
            {
                $this->captured = $body;
                return ['url' => $url, 'headers' => []];
            }

            public function getName(): string
            {
                return 'body_signer';
            }
        };

        $transport = $this->createMockTransport([
            $this->createJsonResponse(['ok' => true]),
        ]);

        $middleware = new RequestSigningMiddleware($bodySigner);
        $wrapped = $middleware->wrap($transport);

        // No body in options
        $wrapped->request('GET', 'https://api.example.com/test', []);

        $this->assertSame('', $capturedBody);
    }
}
