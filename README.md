# Four HTTP Client

A modern PSR-18 HTTP client library for PHP 8.4+ with middleware support. Built for API integrations with generic transport and authentication layers.

## Features

- **PSR-18 Compliant** — Works with any PSR-18 compatible client
- **Middleware Stack** — Logging, rate limiting, retry, authentication
- **Authentication** — Bearer tokens, API keys, OAuth 2.0, OAuth 1.0a
- **Retry Logic** — Exponential backoff with configurable policies
- **Error Mapping** — 401 → AuthenticationException, 404 → NotFoundException, 429 → RateLimitException

## Installation

```bash
composer require four-bytes/four-http-client
```

Requires PHP 8.4+ and PSR packages:
- `psr/http-client: ^1.0`
- `psr/http-factory: ^1.0`
- `psr/http-message: ^2.0`
- `psr/log: ^3.0`

Recommended: `php-http/discovery` for automatic transport discovery.

## Quick Start

```php
use Four\Http\Configuration\ClientConfig;
use Four\Http\Factory\HttpClientFactory;

$factory = new HttpClientFactory();

$config = ClientConfig::create('https://api.example.com')
    ->withAuth('bearer', 'your-token')
    ->withTimeout(30.0)
    ->build();

$client = $factory->create($config);

$response = $client->request('GET', '/api/data');
$data = json_decode($response->getContent(), true);
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
use Four\RateLimit\Policy\TokenBucketPolicy;

$factory = new RateLimiterFactory();
$limiter = $factory->create('api_requests', new TokenBucketPolicy(
    capacity: 20,
    refillRate: 20,
    refillPeriod: 1.0
));

$config = ClientConfig::create('https://api.example.com')
    ->withRateLimit($limiter)
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

// API key
$provider = TokenProvider::apiKey('your-api-key', 'X-API-Key');

// Amazon LWA token
$provider = TokenProvider::amazonLwa('your-lwa-token');

// Discogs token
$provider = TokenProvider::discogs('your-discogs-token');

$config = ClientConfig::create('https://api.example.com')
    ->withAuthentication($provider)
    ->build();
```

### OAuthProvider

OAuth 2.0 with automatic token refresh:

```php
use Four\Http\Authentication\OAuthProvider;

// Amazon LWA OAuth
$amazonAuth = OAuthProvider::amazon(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    httpClient: $psr18Client,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
    refreshToken: 'your-refresh-token'
);

// eBay OAuth
$ebayAuth = OAuthProvider::ebay(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    httpClient: $psr18Client,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
    refreshToken: 'your-refresh-token',
    scopes: ['https://api.ebay.com/oauth/api_scope'],
    sandbox: false
);
```

### OAuth1aProvider

OAuth 1.0a signature (Discogs):

```php
use Four\Http\Authentication\OAuth1aProvider;

$discogsAuth = OAuth1aProvider::discogs(
    consumerKey: 'your-consumer-key',
    consumerSecret: 'your-consumer-secret',
    accessToken: 'your-access-token',
    tokenSecret: 'your-token-secret'
);

// Test signature generation
$testResult = $discogsAuth->testSignature();
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
use Four\Http\Exception\HttpClientException;
use Four\Http\Exception\AuthenticationException;
use Four\Http\Exception\NotFoundException;
use Four\Http\Exception\RateLimitException;
use Four\Http\Exception\RetryableException;

try {
    $response = $client->request('GET', '/api/data');
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
```

## Requirements

- **PHP 8.4+**
- **psr/http-client: ^1.0**
- **psr/http-factory: ^1.0**
- **psr/http-message: ^2.0**
- **psr/log: ^3.0**
- **php-http/discovery: ^1.19**

Optional:
- **symfony/http-client** — Alternative transport
- **guzzlehttp/guzzle** — Alternative transport

## License

MIT License - see [LICENSE](LICENSE) file.
