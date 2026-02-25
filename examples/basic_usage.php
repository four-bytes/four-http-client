<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\Http\Configuration\ClientConfig;
use Four\Http\Configuration\RetryConfig;
use Four\Http\Factory\MarketplaceHttpClientFactory;
use Four\Http\Authentication\TokenProvider;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\NullLogger;

/**
 * Basic usage example for four-http-client
 *
 * Demonstrates:
 * - Factory creation
 * - ClientConfig with Builder
 * - PSR-18 client creation
 * - Request execution via PSR-18
 */

// Get PSR-17/PSR-18 factories via discovery
$requestFactory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
$streamFactory = \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();
$uriFactory = \Http\Discovery\Psr17FactoryDiscovery::findUriFactory();

echo "Four HTTP Client - Basic Usage\n";
echo "=============================\n\n";

// Create factory (optionally with logger and cache)
$logger = new NullLogger();
$factory = new MarketplaceHttpClientFactory($logger);

// Example 1: Minimal client
echo "1. Minimal client:\n";

$config = ClientConfig::create('https://httpbin.org')
    ->withTimeout(10.0)
    ->build();

$client = $factory->create($config);

echo "   Base URI: {$config->baseUri}\n";
echo "   Timeout: {$config->timeout}s\n\n";

// Example 2: Client with authentication
echo "2. Client with bearer token:\n";

$authProvider = TokenProvider::bearer('my-secret-token');

$config = ClientConfig::create('https://api.example.com')
    ->withAuthentication($authProvider)
    ->withTimeout(30.0)
    ->withLogging($logger)
    ->build();

$client = $factory->create($config);
echo "   Authentication: {$authProvider->getType()}\n";
echo "   Middleware: " . implode(', ', $config->middleware) . "\n\n";

// Example 3: Simple auth helper
echo "3. Simple auth (withAuth helper):\n";

$config = ClientConfig::create('https://api.example.com')
    ->withAuth('bearer', 'token-value')
    ->withAuth('api_key', 'api-key-value', ['header' => 'X-API-Key'])
    ->build();

echo "   Headers include Authorization: " . (isset($config->defaultHeaders['Authorization']) ? 'yes' : 'no') . "\n\n";

// Example 4: Retry configuration
echo "4. Retry configuration:\n";

$retryConfig = RetryConfig::default();
echo "   Default: {$retryConfig->maxAttempts} attempts, initial delay {$retryConfig->initialDelay}s\n";

$retryConfig = RetryConfig::conservative();
echo "   Conservative: {$retryConfig->maxAttempts} attempts, initial delay {$retryConfig->initialDelay}s\n";

$retryConfig = RetryConfig::aggressive();
echo "   Aggressive: {$retryConfig->maxAttempts} attempts, initial delay {$retryConfig->initialDelay}s\n";

$retryConfig = RetryConfig::forMarketplace('amazon');
echo "   Amazon: {$retryConfig->maxAttempts} attempts, max delay {$retryConfig->maxDelay}s\n\n";

// Example 5: Middleware via builder methods
echo "5. Middleware via builder:\n";

$config = ClientConfig::create('https://api.example.com')
    ->withLogging($logger)
    ->withRetries(RetryConfig::default())
    ->build();

echo "   Enabled middleware: " . implode(', ', $config->middleware) . "\n\n";

// Example 6: Custom headers
echo "6. Custom headers:\n";

$config = ClientConfig::create('https://api.example.com')
    ->withHeaders([
        'Accept' => 'application/json',
        'X-Custom-Header' => 'custom-value'
    ])
    ->withUserAgent('MyApp/1.0')
    ->withAccept('application/vnd.api+json')
    ->withContentType('application/json')
    ->build();

echo "   Headers count: " . count($config->defaultHeaders) . "\n";
foreach ($config->defaultHeaders as $name => $value) {
    echo "   - {$name}: {$value}\n";
}
echo "\n";

// Example 7: Marketplace presets
echo "7. Marketplace presets:\n";

$amazon = ClientConfig::create('https://sellingpartnerapi.amazon.com')->forAmazon()->build();
echo "   Amazon: timeout={$amazon->timeout}s, middleware=" . implode(',', $amazon->middleware) . "\n";

$ebay = ClientConfig::create('https://api.ebay.com')->forEbay()->build();
echo "   eBay: timeout={$ebay->timeout}s, middleware=" . implode(',', $ebay->middleware) . "\n";

$discogs = ClientConfig::create('https://api.discogs.com')->forDiscogs()->build();
echo "   Discogs: timeout={$discogs->timeout}s, middleware=" . implode(',', $discogs->middleware) . "\n";

$dev = ClientConfig::create('http://localhost:8080')->forDevelopment()->build();
echo "   Development: timeout={$dev->timeout}s, middleware=" . implode(',', $dev->middleware) . "\n\n";

// Example 8: Make actual request via PSR-18
echo "8. Making GET request (PSR-18):\n";

try {
    // Create PSR-7 request
    $uri = $uriFactory->createUri('https://httpbin.org/get?foo=bar');
    $request = $requestFactory->createRequest('GET', $uri);
    
    // Send via PSR-18 client
    $response = $client->sendRequest($request);
    
    echo "   Status: {$response->getStatusCode()}\n";
    echo "   Content-Type: " . ($response->getHeaderLine('content-type') ?: 'none') . "\n";
    
    $data = json_decode($response->getBody()->getContents(), true);
    if ($data && isset($data['args'])) {
        echo "   Query args: " . json_encode($data['args']) . "\n";
    }
    
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n";
}

echo "\n" . str_repeat('=', 40) . "\n";
echo "Basic usage examples completed.\n";
