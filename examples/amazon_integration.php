<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\Http\Configuration\ClientConfig;
use Four\Http\Configuration\RetryConfig;
use Four\Http\Factory\MarketplaceHttpClientFactory;
use Four\Http\Authentication\TokenProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\NullLogger;

/**
 * Amazon SP-API Integration Example
 *
 * Demonstrates:
 * - TokenProvider for LWA authentication
 * - ClientConfig with forAmazon() preset
 * - Rate limiting and retry configuration
 * - Marketplace-specific headers
 */

echo "Four HTTP Client - Amazon SP-API Integration\n";
echo "============================================\n\n";

$logger = new NullLogger();
$factory = new MarketplaceHttpClientFactory($logger);

// Example 1: Simple token authentication
echo "1. Simple LWA token authentication:\n";

$authProvider = TokenProvider::amazonLwa('your-lwa-access-token');

$config = ClientConfig::create('https://sellingpartnerapi-eu.amazon.com')
    ->forAmazon()
    ->withAuthentication($authProvider)
    ->withHeaders([
        'x-amzn-marketplace-id' => 'A1PA6795UKMFR9'  // Germany
    ])
    ->build();

$client = $factory->create($config);

echo "   Base URI: {$config->baseUri}\n";
echo "   Timeout: {$config->timeout}s\n";
echo "   Middleware: " . implode(', ', $config->middleware) . "\n\n";

// Example 2: OAuth 2.0 setup (requires PSR-18 client)
echo "2. OAuth 2.0 with token refresh:\n";

echo "   // OAuthProvider requires PSR-18 client:\n";
echo "   \$oauthProvider = OAuthProvider::amazon(\n";
echo "       clientId: 'your-client-id',\n";
echo "       clientSecret: 'your-client-secret',\n";
echo "       httpClient: \$psr18Client,    // PSR-18 ClientInterface\n";
echo "       requestFactory: \$requestFactory,\n";
echo "       streamFactory: \$streamFactory,\n";
echo "       refreshToken: 'your-refresh-token'\n";
echo "   );\n\n";

// Example 3: Marketplace presets
echo "3. Marketplace configurations:\n";

$marketplaces = [
    'DE' => ['id' => 'A1PA6795UKMFR9', 'endpoint' => 'https://sellingpartnerapi-eu.amazon.com'],
    'UK' => ['id' => 'A1F83G8C2ARO7P', 'endpoint' => 'https://sellingpartnerapi-eu.amazon.com'],
    'FR' => ['id' => 'A13V1IB3VIYZZH', 'endpoint' => 'https://sellingpartnerapi-eu.amazon.com'],
    'US' => ['id' => 'ATVPDKIKX0DER', 'endpoint' => 'https://sellingpartnerapi-us.amazon.com'],
];

foreach ($marketplaces as $country => $info) {
    $config = ClientConfig::create($info['endpoint'])
        ->forAmazon()
        ->withAuthentication($authProvider)
        ->withHeaders(['x-amzn-marketplace-id' => $info['id']])
        ->build();
    
    echo "   {$country}: marketplace-id={$info['id']}, timeout={$config->timeout}s\n";
}

echo "\n";

// Example 4: Retry configuration
echo "4. Retry configuration for Amazon:\n";

$retryConfig = RetryConfig::forMarketplace('amazon');
echo "   Max attempts: {$retryConfig->maxAttempts}\n";
echo "   Initial delay: {$retryConfig->initialDelay}s\n";
echo "   Multiplier: {$retryConfig->multiplier}\n";
echo "   Max delay: {$retryConfig->maxDelay}s\n";
echo "   Retryable codes: " . implode(', ', $retryConfig->retryableStatusCodes) . "\n\n";

// Example 5: Error handling
echo "5. Error handling:\n";
echo "   401 (Unauthorized): AuthenticationException - refresh LWA token\n";
echo "   429 (Too Many Requests): RateLimitException - wait and retry\n";
echo "   500/502/503/504: RetryableException - exponential backoff\n\n";

// Example 6: Making SP-API requests (mock)
echo "6. Making SP-API requests (example):\n";

echo "   // Setup request using PSR-17\n";
echo "   \$uriFactory = \$psr17Factory->createUri(...);\n";
echo "   \$request = \$requestFactory->createRequest('GET', \$uri);\n\n";

echo "   // Send request via PSR-18\n";
echo "   \$response = \$client->sendRequest(\$request);\n\n";

echo "   // Orders API: GET /orders/v0/orders\n";
echo "   // Feeds API: POST /feeds/2021-06-30/feeds\n\n";

echo str_repeat('=', 45) . "\n";
echo "Amazon SP-API examples completed.\n";
echo "See Amazon SP-API documentation for endpoint details.\n";
