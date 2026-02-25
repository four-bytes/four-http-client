<?php

declare(strict_types=1);

namespace Four\Http\Transport;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 Adapter über HttpTransportInterface
 *
 * Konvertiert PSR-7 RequestInterface in Transport-Requests
 * und gibt PSR-7 ResponseInterface zurück.
 */
class TransportPsr18Adapter implements ClientInterface
{
    public function __construct(
        private readonly HttpTransportInterface $transport,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $options = [];

        // Headers übertragen
        foreach ($request->getHeaders() as $name => $values) {
            $options['headers'][$name] = implode(', ', $values);
        }

        // Body übertragen
        $body = (string) $request->getBody();
        if ($body !== '') {
            $options['body'] = $body;
        }

        // Die vollständige URI (inkl. Query-String) wird direkt übergeben.
        // Query-Parameter müssen NICHT separat in $options['query'] gesetzt werden,
        // da sie bereits in der URI enthalten sind (z.B. ?page=1&limit=10).
        $transportResponse = $this->transport->request(
            $request->getMethod(),
            (string) $request->getUri(),
            $options,
        );

        return new TransportPsr7Response($transportResponse);
    }
}
