<?php

declare(strict_types=1);

namespace Four\Http\Tests\Client;

use Four\Http\Client\ApiClient;

/** Minimal concrete client exposing httpGet() for tests. */
final class TestApiClient extends ApiClient
{
    /** @return array<string, mixed> */
    public function get(string $url): array
    {
        return $this->httpGet($url);
    }
}
