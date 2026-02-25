<?php

declare(strict_types=1);

namespace Four\Http\Exception;

/**
 * Base exception for HTTP client operations.
 *
 * All exceptions thrown by the Four\Http library extend this base exception,
 * providing a consistent exception hierarchy for error handling.
 */
class HttpClientException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        protected readonly ?string $operation = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the operation associated with this exception
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }
}
