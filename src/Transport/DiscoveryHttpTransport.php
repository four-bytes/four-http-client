<?php

declare(strict_types=1);

namespace Four\Http\Transport;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * PSR-18 Transport via php-http/discovery.
 *
 * Uses a discovered PSR-18 client and PSR-17 request factory
 * to execute HttpTransportInterface requests.
 */
class DiscoveryHttpTransport implements HttpTransportInterface
{
    /** @param array<string, mixed> $defaultOptions */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly array $defaultOptions = [],
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $mergedOptions = array_merge($this->defaultOptions, $options);

        // Extract headers from options
        /** @var array<string, string|string[]> $headers */
        $headers = $mergedOptions['headers'] ?? [];

        // Body from options
        $body = isset($mergedOptions['body']) && is_string($mergedOptions['body'])
            ? $mergedOptions['body']
            : null;

        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $request = $requestFactory->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $request = $request->withBody($streamFactory->createStream($body));
        }

        $response = $this->client->sendRequest($request);

        return new Psr7HttpResponse($response);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        // @phpstan-ignore new.static
        return new static($this->client, array_merge($this->defaultOptions, $options));
    }
}
