<?php

declare(strict_types=1);

namespace Four\Http\Exception;

/**
 * Exception thrown when authentication fails.
 *
 * This exception is thrown when HTTP requests fail due to authentication
 * issues such as invalid credentials, expired tokens, or missing authentication.
 */
class AuthenticationException extends HttpClientException
{
    public function __construct(
        string $message = 'Authentication failed',
        int $code = 401,
        ?\Throwable $previous = null,
        ?string $operation = null,
        private readonly ?string $authType = null
    ) {
        parent::__construct($message, $code, $previous, $operation);
    }

    /**
     * Get the authentication type that failed
     */
    public function getAuthType(): ?string
    {
        return $this->authType;
    }

    /**
     * Create exception for expired token
     */
    public static function tokenExpired(?string $operation = null): self
    {
        return new self(
            'Authentication token has expired',
            401,
            null,
            $operation,
            'token'
        );
    }

    /**
     * Create exception for invalid credentials
     */
    public static function invalidCredentials(?string $operation = null): self
    {
        return new self(
            'Invalid authentication credentials',
            401,
            null,
            $operation,
            'credentials'
        );
    }

    /**
     * Create exception for missing authentication
     */
    public static function missing(?string $operation = null): self
    {
        return new self(
            'Authentication required but not provided',
            401,
            null,
            $operation,
            'missing'
        );
    }

    /**
     * Create exception for insufficient permissions
     */
    public static function insufficientPermissions(?string $operation = null): self
    {
        return new self(
            'Insufficient permissions for this operation',
            403,
            null,
            $operation,
            'permissions'
        );
    }
}
