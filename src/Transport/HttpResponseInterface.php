<?php

declare(strict_types=1);

namespace Four\Http\Transport;

interface HttpResponseInterface
{
    public function getStatusCode(): int;

    /**
     * @return array<string, string|array<string>>
     */
    public function getHeaders(bool $throw = true): array;

    public function getContent(bool $throw = true): string;

    /**
     * @return array<mixed>
     */
    public function toArray(bool $throw = true): array;
}
