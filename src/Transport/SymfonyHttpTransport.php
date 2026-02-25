<?php

declare(strict_types=1);

namespace Four\Http\Transport;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyHttpTransport implements HttpTransportInterface
{
    public function __construct(private readonly HttpClientInterface $client) {}

    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        return new SymfonyHttpResponse($this->client->request($method, $url, $options));
    }

    public function withOptions(array $options): static
    {
        return new static($this->client->withOptions($options));
    }
}
