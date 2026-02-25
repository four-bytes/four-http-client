<?php

declare(strict_types=1);

namespace Four\Http\Middleware;

use Four\Http\Configuration\RetryConfig;
use Four\Http\Exception\RetryableException;
use Four\Http\Transport\HttpResponseInterface;
use Four\Http\Transport\HttpTransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Middleware that adds retry functionality to HTTP requests.
 *
 * Automatically retries failed requests based on configurable retry strategies,
 * with exponential backoff and configurable retry conditions.
 */
class RetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RetryConfig $retryConfig,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function wrap(HttpTransportInterface $transport): HttpTransportInterface
    {
        return new RetryHttpTransport(
            $transport,
            $this->retryConfig,
            $this->logger,
        );
    }

    public function getName(): string
    {
        return 'retry';
    }

    public function getPriority(): int
    {
        return 50; // Apply retries after rate limiting but before logging final result
    }
}

/**
 * HTTP Transport decorator that adds retry functionality
 */
class RetryHttpTransport implements HttpTransportInterface
{
    use SanitizesUrl;

    public function __construct(
        private readonly HttpTransportInterface $transport,
        private readonly RetryConfig $retryConfig,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->retryConfig->maxAttempts) {
            try {
                $response = $this->transport->request($method, $url, $options);

                // Check if response status code indicates we should retry
                $statusCode = $response->getStatusCode();
                if ($attempt < $this->retryConfig->maxAttempts && $this->retryConfig->shouldRetryStatusCode($statusCode)) {
                    $this->logger->warning('HTTP request returned retryable status code', [
                        'method' => $method,
                        'url' => $this->sanitizeUrl($url),
                        'status_code' => $statusCode,
                        'attempt' => $attempt,
                        'max_attempts' => $this->retryConfig->maxAttempts,
                    ]);

                    $this->waitBeforeRetry($attempt);
                    $attempt++;
                    continue;
                }

                // Success or non-retryable error
                if ($attempt > 1) {
                    $this->logger->info('HTTP request succeeded after retries', [
                        'method' => $method,
                        'url' => $this->sanitizeUrl($url),
                        'attempts' => $attempt,
                        'status_code' => $statusCode,
                    ]);
                }

                return $response;

            } catch (\Exception $exception) {
                $lastException = $exception;

                // Check if this exception is retryable
                if ($attempt < $this->retryConfig->maxAttempts && $this->shouldRetryException($exception)) {
                    $this->logger->warning('HTTP request failed with retryable exception', [
                        'method' => $method,
                        'url' => $this->sanitizeUrl($url),
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'attempt' => $attempt,
                        'max_attempts' => $this->retryConfig->maxAttempts,
                    ]);

                    $this->waitBeforeRetry($attempt);
                    $attempt++;
                    continue;
                }

                // Not retryable or out of attempts
                break;
            }
        }

        // If we get here, we've exhausted all retry attempts
        if ($lastException !== null) {
            if ($attempt > 1) {
                $this->logger->error('HTTP request failed after all retry attempts', [
                    'method' => $method,
                    'url' => $this->sanitizeUrl($url),
                    'attempts' => $attempt - 1,
                    'final_exception' => get_class($lastException),
                    'final_message' => $lastException->getMessage(),
                ]);

                // Wrap the final exception with retry context
                throw RetryableException::fromException(
                    $lastException,
                    $this->extractOperationFromUrl($url),
                    $attempt - 1,
                    $this->retryConfig->maxAttempts,
                    0.0
                );
            }

            throw $lastException;
        }

        // This should not happen, but just in case
        throw new \RuntimeException('Unexpected end of retry loop');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        // @phpstan-ignore new.static
        return new static(
            $this->transport->withOptions($options),
            $this->retryConfig,
            $this->logger,
        );
    }

    /**
     * Check if an exception should trigger a retry
     */
    private function shouldRetryException(\Exception $exception): bool
    {
        // Use configured retryable exceptions
        if ($this->retryConfig->shouldRetryException($exception)) {
            return true;
        }

        // Retry on network/transport errors (RuntimeException mit passenden Messages)
        $message = strtolower($exception->getMessage());
        if (
            $exception instanceof \RuntimeException
            && (
                str_contains($message, 'network')
                || str_contains($message, 'connection')
                || str_contains($message, 'timeout')
                || str_contains($message, 'transport')
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * Wait before retry with exponential backoff
     */
    private function waitBeforeRetry(int $attempt): void
    {
        $delay = $this->retryConfig->calculateDelay($attempt);

        if ($delay > 0) {
            $this->logger->debug('Waiting before retry', [
                'attempt' => $attempt,
                'delay_seconds' => $delay,
            ]);

            usleep((int) ($delay * 1000000)); // Convert seconds to microseconds
        }
    }

    /**
     * Extract operation name from URL for context
     */
    private function extractOperationFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        // Extract operation name from path
        $pathParts = explode('/', trim($path, '/'));

        return $pathParts[count($pathParts) - 1] ?: 'unknown';
    }
}
