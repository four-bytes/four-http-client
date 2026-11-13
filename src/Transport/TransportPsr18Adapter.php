<?php

declare(strict_types=1);

namespace Four\Http\Transport;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 adapter on top of HttpTransportInterface
 *
 * Converts a PSR-7 RequestInterface into transport requests
 * and returns a PSR-7 ResponseInterface.
 */
class TransportPsr18Adapter implements ClientInterface
{
    public function __construct(
        private readonly HttpTransportInterface $transport,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $options = [];

        // Forward headers
        foreach ($request->getHeaders() as $name => $values) {
            $options['headers'][$name] = implode(', ', $values);
        }

        // Forward body
        $body = (string) $request->getBody();
        if ($body !== '') {
            $options['body'] = $body;
        }

        // The full URI (including the query string) is passed directly.
        // Query parameters must NOT be set separately in $options['query'],
        // since they are already part of the URI (e.g. ?page=1&limit=10).
        $transportResponse = $this->transport->request(
            $request->getMethod(),
            (string) $request->getUri(),
            $options,
        );

        return new TransportPsr7Response($transportResponse);
    }
}
