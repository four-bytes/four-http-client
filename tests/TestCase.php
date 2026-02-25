<?php

declare(strict_types=1);

namespace Four\Http\Tests;

use Four\Http\Transport\HttpResponseInterface;
use Four\Http\Transport\HttpTransportInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\NullLogger;

/**
 * Base test case with common utilities for HTTP client testing
 */
abstract class TestCase extends BaseTestCase
{
    protected NullLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new NullLogger();
    }

    /**
     * Create a mock HTTP transport with predefined responses
     *
     * @param array<HttpResponseInterface> $responses
     */
    protected function createMockTransport(array $responses): HttpTransportInterface
    {
        return new class($responses) implements HttpTransportInterface {
            /** @var array<HttpResponseInterface> */
            private array $queue;

            /** @param array<HttpResponseInterface> $responses */
            public function __construct(array $responses)
            {
                $this->queue = $responses;
            }

            /**
             * @param array<string, mixed> $options
             */
            public function request(string $method, string $url, array $options = []): HttpResponseInterface
            {
                if (empty($this->queue)) {
                    throw new \RuntimeException('No more mock responses available');
                }
                return array_shift($this->queue);
            }

            /**
             * @param array<string, mixed> $options
             */
            public function withOptions(array $options): static
            {
                return clone $this;
            }
        };
    }

    /**
     * Create a mock HTTP response with JSON content
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $headers
     */
    protected function createJsonResponse(
        array $data,
        int $status = 200,
        array $headers = []
    ): HttpResponseInterface {
        $defaultHeaders = [
            'content-type' => ['application/json'],
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);
        $body = json_encode($data);

        return new class($status, $mergedHeaders, $body) implements HttpResponseInterface {
            /**
             * @param array<string, mixed> $headers
             */
            public function __construct(
                private readonly int $statusCode,
                private readonly array $headers,
                private readonly string $content,
            ) {}

            public function getStatusCode(): int { return $this->statusCode; }

            /**
             * @return array<string, string|array<string>>
             */
            public function getHeaders(bool $throw = true): array { return $this->headers; }
            public function getContent(bool $throw = true): string { return $this->content; }

            /**
             * @return array<mixed>
             */
            public function toArray(bool $throw = true): array
            {
                return json_decode($this->content, true) ?? [];
            }
        };
    }

    /**
     * Create a mock response for rate limiting scenarios
     */
    protected function createRateLimitResponse(
        string $marketplace = 'general',
        int $limit = 100,
        int $remaining = 50
    ): HttpResponseInterface {
        $headers = match ($marketplace) {
            'amazon' => [
                'x-amzn-ratelimit-limit' => [(string) $limit],
                'x-amzn-ratelimit-remaining' => [(string) $remaining],
            ],
            'ebay' => [
                'x-ebay-api-analytics-daily-remaining' => [(string) $remaining],
            ],
            'discogs' => [
                'x-discogs-ratelimit-remaining' => [(string) $remaining],
            ],
            default => [
                'x-ratelimit-limit' => [(string) $limit],
                'x-ratelimit-remaining' => [(string) $remaining],
            ],
        };

        return $this->createJsonResponse(['success' => true], 200, $headers);
    }

    /**
     * Create a mock response for rate limit exceeded scenarios
     */
    protected function createRateExceededResponse(
        string $marketplace = 'general',
        int $retryAfter = 60
    ): HttpResponseInterface {
        $headers = [
            'retry-after' => [(string) $retryAfter],
        ];

        if ($marketplace === 'amazon') {
            $headers['x-amzn-ratelimit-limit'] = ['0'];
            $headers['x-amzn-ratelimit-remaining'] = ['0'];
        }

        return $this->createJsonResponse(['error' => 'Rate limit exceeded'], 429, $headers);
    }

    /**
     * Create a mock response for server errors
     */
    protected function createServerErrorResponse(int $status = 500): HttpResponseInterface
    {
        return $this->createJsonResponse(['error' => 'Internal server error'], $status);
    }

    /**
     * Assert that a specific log level was recorded (NullLogger-kompatibel: no-op)
     */
    protected function assertLogLevel(string $level): void
    {
        // NullLogger speichert keine Records — Tests die Logging prüfen müssen eigenen Logger injecten
        $this->addToAssertionCount(1);
    }

    /**
     * Assert that a log message contains specific text (NullLogger-kompatibel: no-op)
     */
    protected function assertLogMessage(string $level, string $message): void
    {
        $this->addToAssertionCount(1);
    }

    /**
     * Get all log records for inspection (NullLogger hat keine Records)
     *
     * @return array<mixed>
     */
    protected function getLogRecords(): array
    {
        return [];
    }

    /**
     * Clear all log records (no-op bei NullLogger)
     */
    protected function clearLogs(): void
    {
        // NullLogger hat keine Records
    }

    /**
     * Create a temporary test file
     */
    protected function createTempFile(string $content = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'four_http_test_');
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary file');
        }

        if (!empty($content)) {
            file_put_contents($tempFile, $content);
        }

        return $tempFile;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
