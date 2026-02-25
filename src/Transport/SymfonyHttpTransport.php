<?php

declare(strict_types=1);

namespace Four\Http\Transport;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyHttpTransport implements HttpTransportInterface
{
    public function __construct(private readonly HttpClientInterface $client) {}

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        return new SymfonyHttpResponse($this->client->request($method, $url, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        // @phpstan-ignore new.static
        return new static($this->client->withOptions($options));
    }
}
