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
        $transport = new class implements HttpTransportInterface {
            /** @var array<string, mixed>|null */
            public ?array $captured = null;

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

        $capturedOptions = $transport->captured;
        $this->assertNotNull($capturedOptions);
        $this->assertArrayHasKey('headers', $capturedOptions);
        /** @var array<string, string> $headers */
        $headers = $capturedOptions['headers'];
        $this->assertArrayHasKey('X-Signature', $headers);
        $this->assertSame('test-sig-get', $headers['X-Signature']);
    }

    public function testSigningMergesWithExistingHeaders(): void
    {
        $transport = new class implements HttpTransportInterface {
            /** @var array<string, mixed>|null */
            public ?array $captured = null;

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

        $capturedOptions = $transport->captured;
        $this->assertNotNull($capturedOptions);
        $this->assertArrayHasKey('headers', $capturedOptions);
        /** @var array<string, string> $headers */
        $headers = $capturedOptions['headers'];
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Signature', $headers);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('test-sig-post', $headers['X-Signature']);
    }

    public function testSigningCanModifyUrl(): void
    {
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

        $transport = new class implements HttpTransportInterface {
            public ?string $captured = null;

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

        $this->assertSame('https://api.example.com/test?sign=abc123', $transport->captured);
    }

    public function testSigningPassesBodyToSigner(): void
    {
        $bodySigner = new class implements RequestSignerInterface {
            public ?string $captured = null;

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

        $this->assertSame('{"key":"value"}', $bodySigner->captured);
    }

    public function testSigningPassesMethodToSigner(): void
    {
        $methodSigner = new class implements RequestSignerInterface {
            public ?string $captured = null;

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

        $this->assertSame('DELETE', $methodSigner->captured);
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
        $bodySigner = new class implements RequestSignerInterface {
            public ?string $captured = null;

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

        $this->assertSame('', $bodySigner->captured);
    }
}
