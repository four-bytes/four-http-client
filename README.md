# Four HTTP Client

PSR-18 HTTP client factory and base `ApiClient` for PHP 8.4+ — the shared foundation for building typed API clients with authentication, rate limiting, retries and error mapping.

[![Packagist Version](https://img.shields.io/packagist/v/four-bytes/four-http-client)](https://packagist.org/packages/four-bytes/four-http-client)
[![PHP Version](https://img.shields.io/packagist/dependency-v/four-bytes/four-http-client/php)](https://packagist.org/packages/four-bytes/four-http-client)
[![License](https://img.shields.io/packagist/l/four-bytes/four-http-client)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/four-bytes/four-http-client)](https://packagist.org/packages/four-bytes/four-http-client)

## Features

- **PSR-18 compliant** — works with any PSR-18 client, discovered via `php-http/discovery`
- **Middleware stack** — logging, rate limiting, retry, authentication, request signing
- **Authentication** — bearer tokens, API keys, OAuth 2.0 (`client_secret_post` / `client_secret_basic`), OAuth 1.0a
- **Retry logic** — exponential backoff with configurable policies
- **Error mapping** — 401 → `AuthenticationException`, 404 → `NotFoundException`, 429 → `RateLimitException`, 5xx → `RetryableException`
- **No platform presets** — endpoints, auth methods and rate-limit headers of a specific API live in the API client built on top

## Requirements

- PHP 8.4+
- A PSR-18 client and PSR-17 factories (e.g. `symfony/http-client` + `nyholm/psr7`, or `guzzlehttp/guzzle`), found automatically via `php-http/discovery`
- `psr/http-client` ^1.0, `psr/http-factory` ^1.0, `psr/http-message` ^2.0, `psr/log` ^3.0
- [`four-bytes/four-rate-limiting`](https://github.com/four-bytes/four-rate-limiting) ^1.3 (installed automatically)

## Installation

```bash
composer require four-bytes/four-http-client
```

## Quick Start

### Option 1: PSR-18 Client with HttpClientFactory

Build a PSR-18 compliant client and use it directly with PSR-17 request factories:

```php
use Four\Http\Configuration\ClientConfig;
use Four\Http\Factory\HttpClientFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

// Build a PSR-18 client with middleware stack
$factory = new HttpClientFactory();

$config = ClientConfig::create('https://api.example.com')
    ->withAuth('bearer', 'your-token')
    ->withTimeout(30.0)
    ->build();

$psrClient = $factory->create($config);

// Use PSR-17 request factory to build requests
$requestFactory = new Psr17Factory();
$request = $requestFactory->createRequest('GET', 'https://api.example.com/data');

$response = $psrClient->sendRequest($request);
$data = json_decode((string) $response->getBody(), true);
```

### Option 2: Extend ApiClient for Custom API Clients

Create a typed client for your specific API:

```php
use Four\Http\Client\ApiClient;
use Four\Http\Configuration\ClientConfig;
use Four\Http\Factory\HttpClientFactory;
use Four\Http\Authentication\TokenProvider;
use Nyholm\Psr7\Factory\Psr17Factory;

class MyApiClient extends ApiClient
{
    public function getUser(int $id): array
    {
        return $this->httpGet('/users/' . $id); // resolved against the base URI
    }

    public function createUser(array $data): array
    {
        return $this->httpPost('/users', $data);
    }

    public function updateUser(int $id, array $data): array
    {
        return $this->httpPatch('/users/' . $id, $data);
    }

    public function deleteUser(int $id): void
    {
        $this->httpDelete('/users/' . $id);
    }
}

$config = ClientConfig::create('https://api.example.com')
    ->withTimeout(30.0)
    ->build();

$psr17 = new Psr17Factory();
$client = new MyApiClient(
    $config,
    (new HttpClientFactory())->create($config),
    $psr17,                                  // RequestFactoryInterface
    $psr17,                                  // StreamFactoryInterface
    TokenProvider::bearer('your-token'),     // optional AuthProviderInterface
);

$user = $client->getUser(42);
$newUser = $client->createUser(['name' => 'John']);
```

## Configuration

### ClientConfigBuilder

Fluent builder for client configuration:

```php
$config = ClientConfig::create('https://api.example.com')
    // Authentication (simple)
    ->withAuth('bearer', 'your-token')
    // or with AuthProvider
    ->withAuthentication($authProvider)
    
    // Headers
    ->withHeaders(['X-Custom-Header' => 'value'])
    ->withUserAgent('MyApp/1.0')
    ->withAccept('application/json')
    ->withContentType('application/json')
    
    // Timeouts
    ->withTimeout(30.0)
    ->withMaxRedirects(3)
    
    // Middleware (enabled via methods below)
    ->withLogging($logger)
    ->withRateLimit($rateLimiter)
    ->withRetries($retryConfig)
    
    ->build();
```

### Retry Configuration

```php
use Four\Http\Configuration\RetryConfig;

// Default: 3 attempts, exponential backoff
$config = RetryConfig::default();

// Conservative for rate-limited APIs
$config = RetryConfig::conservative();

// Aggressive for robust APIs
$config = RetryConfig::aggressive();

// Custom
$config = new RetryConfig(
    maxAttempts: 5,
    initialDelay: 1.0,
    multiplier: 2.0,
    maxDelay: 60.0,
    retryableStatusCodes: [429, 500, 502, 503, 504]
);

// Simple method on builder
$config = ClientConfig::create('https://api.example.com')
    ->withRetryPolicy(maxAttempts: 3, retryableStatusCodes: [429, 500, 502, 503, 504])
    ->build();
```

## Middleware

### Logging

```php
use Psr\Log\NullLogger;

$config = ClientConfig::create('https://api.example.com')
    ->withLogging(new NullLogger())
    // or with custom logger
    ->withLogging($myPsr3Logger)
    ->build();
```

### Rate Limiting

Requires `four-bytes/four-rate-limiting`:

```php
use Four\RateLimit\RateLimiterFactory;
use Four\RateLimit\RateLimitConfiguration;

$config = new RateLimitConfiguration(
    algorithm: RateLimitConfiguration::ALGORITHM_TOKEN_BUCKET,
    ratePerSecond: 10.0,
    burstCapacity: 20,
);

$rateLimiter = (new RateLimiterFactory())->create($config);

$clientConfig = ClientConfig::create('https://api.example.com')
    ->withRateLimit($rateLimiter)
    ->build();
```

### Retry

```php
$config = ClientConfig::create('https://api.example.com')
    ->withRetries(RetryConfig::default())
    ->build();
```

### Custom Middleware

Implement `Four\Http\Middleware\MiddlewareInterface`:

```php
use Four\Http\Middleware\MiddlewareInterface;
use Four\Http\Transport\HttpTransportInterface;

class CustomMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger) {}
    
    public function wrap(HttpTransportInterface $transport): HttpTransportInterface
    {
        return new CustomTransportWrapper($transport, $this->logger);
    }
    
    public function getName(): string
    {
        return 'custom';
    }
    
    public function getPriority(): int
    {
        return 100;
    }
}

$config = ClientConfig::create('https://api.example.com')
    ->withMiddleware(['logging', new CustomMiddleware($logger)])
    ->build();
```

## Authentication

### TokenProvider

Simple bearer/API tokens:

```php
use Four\Http\Authentication\TokenProvider;

// Bearer token (default)
$provider = TokenProvider::bearer('your-access-token');

// API key with custom header
$provider = TokenProvider::apiKey('your-api-key', 'X-API-Key');

// Custom header/prefix
$provider = new TokenProvider('your-token', 'X-Custom-Auth', 'Token');

$config = ClientConfig::create('https://api.example.com')
    ->withAuthentication($provider)
    ->build();
```

### OAuthProvider

OAuth 2.0 with automatic token refresh:

```php
use Four\Http\Authentication\ClientAuthMethod;
use Four\Http\Authentication\OAuthProvider;

// OAuth 2.0 with client credentials or refresh token flow
$auth = new OAuthProvider(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    tokenEndpoint: 'https://api.example.com/oauth/token',
    httpClient: $psr18Client,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
    refreshToken: 'your-refresh-token',   // optional
    scopes: ['read', 'write'],            // optional
    clientAuthMethod: ClientAuthMethod::ClientSecretBasic, // optional, default ClientSecretPost
);
```

`ClientAuthMethod` (RFC 6749 §2.3.1) controls how the client authenticates at the token endpoint:
`ClientSecretPost` sends `client_id`/`client_secret` in the form body, `ClientSecretBasic` sends them
as an HTTP Basic header (required e.g. by eBay). A failed token request reports the endpoint's
`error` / `error_description` in the exception message.

This package ships no platform presets: token endpoints, auth methods and rate-limit header names
of a specific API belong to the API client built on top of it.

### OAuth1aProvider

OAuth 1.0a signature-based authentication:

```php
use Four\Http\Authentication\OAuth1aProvider;

// OAuth 1.0a signature-based authentication
$auth = new OAuth1aProvider(
    consumerKey: 'your-consumer-key',
    consumerSecret: 'your-consumer-secret',
    accessToken: 'your-access-token',
    tokenSecret: 'your-token-secret',
);
```

## Custom Transport

Implement `Four\Http\Transport\HttpTransportInterface` for custom HTTP backends:

```php
use Four\Http\Transport\HttpTransportInterface;
use Four\Http\Transport\HttpResponseInterface;

class MyCustomTransport implements HttpTransportInterface
{
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null
    ): HttpResponseInterface {
        // Your HTTP implementation
        return new MyCustomResponse($statusCode, $headers, $body);
    }
}

$transport = new MyCustomTransport();
$client = new TransportPsr18Adapter($transport);
```

## Error Handling

The library maps HTTP status codes to specific exceptions:

```php
use Four\Http\Client\ApiClient;
use Four\Http\Exception\HttpClientException;
use Four\Http\Exception\AuthenticationException;
use Four\Http\Exception\NotFoundException;
use Four\Http\Exception\RateLimitException;
use Four\Http\Exception\RetryableException;

class MyApiClient extends ApiClient
{
    public function getData(): array
    {
        try {
            return $this->httpGet('/api/data');
        } catch (NotFoundException $e) {
            // 404 - Resource not found
            echo "Not found: " . $e->getMessage();
        } catch (AuthenticationException $e) {
            // 401/403 - Auth failed
            echo "Auth error: " . $e->getMessage();
        } catch (RateLimitException $e) {
            // 429 - Rate limited
            $retryAfter = $e->getRetryAfter();
            sleep($retryAfter);
        } catch (RetryableException $e) {
            // 500, 502, 503, 504 - Server errors, will be retried automatically
            echo "Server error: " . $e->getMessage();
        } catch (HttpClientException $e) {
            // Other HTTP errors
            echo "HTTP error: " . $e->getMessage();
        }

        return [];
    }
}
```

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

PHPStan runs at the level configured in `phpstan.neon`; both must pass before every commit.

## Related packages

- [`four-bytes/four-rate-limiting`](https://github.com/four-bytes/four-rate-limiting) — rate limiting algorithms used by the rate-limiting middleware
- [`four-bytes/four-lexware-api-client`](https://github.com/four-bytes/four-lexware-api-client) — Lexware Office API client built on this package
- [`four-bytes/four-webdav-client`](https://github.com/four-bytes/four-webdav-client), [`four-bytes/four-discord-client`](https://github.com/four-bytes/four-discord-client), [`four-bytes/four-deezer-api-client`](https://github.com/four-bytes/four-deezer-api-client) — further clients on the same foundation

## Contributing, security, changelog

- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)
- Security: please report vulnerabilities privately as described in [SECURITY.md](SECURITY.md)
- Changelog: [HISTORY.md](HISTORY.md)

## License

Apache-2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE).
