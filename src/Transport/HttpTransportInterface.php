<?php

declare(strict_types=1);

namespace Four\Http\Transport;

interface HttpTransportInterface
{
    public function request(string $method, string $url, array $options = []): HttpResponseInterface;

    public function withOptions(array $options): static;
}
