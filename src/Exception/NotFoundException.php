<?php

declare(strict_types=1);

namespace Four\Http\Exception;

/**
 * Exception für nicht gefundene Ressourcen (HTTP 404).
 */
class NotFoundException extends HttpClientException
{
    public static function forResource(string $type, string $id): self
    {
        return new self("{$type} not found: {$id}", 404);
    }
}
