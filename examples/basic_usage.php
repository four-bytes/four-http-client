<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\Http\Authentication\ClientAuthMethod;
use Four\Http\Authentication\OAuthProvider;
use Four\Http\Authentication\TokenProvider;
use Four\Http\Client\ApiClient;
use Four\Http\Configuration\ClientConfig;
use Four\Http\Configuration\RetryConfig;
use Four\Http\Factory\HttpClientFactory;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

/**
 * Basic usage of four-http-client — runs offline (a mock PSR-18 client stands in for the network).
 *
 * 1. Build a config and a PSR-18 client with HttpClientFactory
 * 2. Write a typed API client on top of ApiClient
 * 3. Configure OAuth 2.0 with client_secret_basic
 */

echo "Four HTTP Client — basic usage\n\n";

// 1. Config + PSR-18 client with middleware stack (transport found via php-http/discovery)
$config = ClientConfig::create('https://api.example.com/v1')
    ->withTimeout(10.0)
    ->withUserAgent('MyApp/1.0')
    ->withRetries(RetryConfig::conservative())
    ->build();

try {
    $psrClient = (new HttpClientFactory())->create($config);
    echo "1. PSR-18 client: " . $psrClient::class . ", timeout {$config->timeout}s\n";
} catch (\RuntimeException $e) {
    // php-http/discovery found no PSR-18 client — install one, e.g. symfony/http-client or guzzlehttp/guzzle
    echo "1. HttpClientFactory: no PSR-18 client installed ({$e->getMessage()})\n";
}

// 2. Typed API client
final class UserApi extends ApiClient
{
    /** @return array<string, mixed> */
    public function user(int $id): array
    {
        return $this->httpGet('/users/' . $id); // → https://api.example.com/v1/users/{id}
    }
}

$mock = new MockClient();
$mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"id":42,"name":"Ada"}'));

$psr17 = new Psr17Factory();
$api = new UserApi($config, $mock, $psr17, $psr17, TokenProvider::bearer('demo-token'));
$user = $api->user(42);
$sent = $mock->getLastRequest();

echo "2. UserApi::user(42) → {$user['name']} (request: {$sent->getMethod()} {$sent->getUri()}, "
    . "Authorization: {$sent->getHeaderLine('Authorization')})\n";

// 3. OAuth 2.0 — token endpoint and auth method come from the API you integrate
$oauth = new OAuthProvider(
    clientId: 'client-id',
    clientSecret: 'client-secret',
    tokenEndpoint: 'https://auth.example.com/oauth2/token',
    httpClient: $mock,
    requestFactory: $psr17,
    streamFactory: $psr17,
    refreshToken: 'refresh-token',
    clientAuthMethod: ClientAuthMethod::ClientSecretBasic,
);
$mock->addResponse(new Response(200, [], '{"access_token":"at-123","expires_in":3600}'));
$headers = $oauth->getAuthHeaders();

echo "3. OAuth 2.0 (client_secret_basic): {$headers['Authorization']}\n";
