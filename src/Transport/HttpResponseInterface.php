<?php

declare(strict_types=1);

namespace Four\Http\Transport;

interface HttpResponseInterface
{
    public function getStatusCode(): int;

    public function getHeaders(bool $throw = true): array;

    public function getContent(bool $throw = true): string;

    public function toArray(bool $throw = true): array;
}
