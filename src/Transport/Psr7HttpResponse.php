<?php

declare(strict_types=1);

namespace Four\Http\Transport;

use Psr\Http\Message\ResponseInterface;

/**
 * Adaptiert eine PSR-7 ResponseInterface auf HttpResponseInterface.
 */
class Psr7HttpResponse implements HttpResponseInterface
{
    public function __construct(private readonly ResponseInterface $response) {}

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return array<string, string|array<string>>
     */
    public function getHeaders(bool $throw = true): array
    {
        return $this->response->getHeaders();
    }

    public function getContent(bool $throw = true): string
    {
        return (string) $this->response->getBody();
    }

    /**
     * @return array<mixed>
     */
    public function toArray(bool $throw = true): array
    {
        $content = $this->getContent();
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
