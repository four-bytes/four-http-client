<?php

declare(strict_types=1);

namespace Four\Http\Transport;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Wraps HttpResponseInterface als PSR-7 ResponseInterface
 *
 * Konvertiert eine HttpResponseInterface-Antwort in eine vollständige
 * PSR-7-konforme Response mittels nyholm/psr7.
 */
class TransportPsr7Response implements ResponseInterface
{
    private ResponseInterface $inner;

    public function __construct(HttpResponseInterface $transportResponse)
    {
        $status = $transportResponse->getStatusCode();
        $headers = $transportResponse->getHeaders(false);
        $body = $transportResponse->getContent(false);
        $this->inner = new Response($status, $headers, $body);
    }

    public function getProtocolVersion(): string
    {
        return $this->inner->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withProtocolVersion($version);
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->inner->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->inner->hasHeader($name);
    }

    public function getHeader(string $name): array
    {
        return $this->inner->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->inner->getHeaderLine($name);
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withHeader($name, $value);
        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withAddedHeader($name, $value);
        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withoutHeader($name);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->inner->getBody();
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withBody($body);
        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->inner->getStatusCode();
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withStatus($code, $reasonPhrase);
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->inner->getReasonPhrase();
    }
}
