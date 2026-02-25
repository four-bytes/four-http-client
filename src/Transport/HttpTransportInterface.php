<?php

declare(strict_types=1);

namespace Four\Http\Transport;

interface HttpTransportInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface;

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static;
}
