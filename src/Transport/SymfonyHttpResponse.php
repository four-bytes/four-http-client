<?php

declare(strict_types=1);

namespace Four\Http\Transport;

use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyHttpResponse implements HttpResponseInterface
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
        return $this->response->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        return $this->response->getContent($throw);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(bool $throw = true): array
    {
        return $this->response->toArray($throw);
    }
}
