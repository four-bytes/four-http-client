<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\Http\Configuration\ClientConfig;
use Four\Http\Configuration\RetryConfig;
use Four\Http\Factory\MarketplaceHttpClientFactory;
use Four\Http\Authentication\TokenProvider;
use Psr\Log\NullLogger;

/**
 * Rate Limiting Demonstration
 *
 * Demonstrates rate limiting with four-rate-limiting integration.
 * Requires: four-bytes/four-rate-limiting package
 */

echo "Four HTTP Client - Rate Limiting Demo\n";
echo "====================================\n\n";

// Check if rate limiting package is available
if (!class_exists('Four\RateLimit\RateLimiterFactory')) {
    echo "NOTE: four-rate-limiting package not installed.\n";
    echo "Install with: composer require four-bytes/four-rate-limiting\n\n";
}

$logger = new NullLogger();
$factory = new MarketplaceHttpClientFactory($logger);

// Example 1: Basic rate limiting configuration
echo "1. Rate limiting configuration:\n";

$retryConfig = RetryConfig::forMarketplace('amazon');
echo "   Amazon marketplace: maxAttempts={$retryConfig->maxAttempts}, maxDelay={$retryConfig->maxDelay}s\n";

$retryConfig = RetryConfig::forMarketplace('ebay');
echo "   eBay marketplace: maxAttempts={$retryConfig->maxAttempts}, maxDelay={$retryConfig->maxDelay}s\n";

$retryConfig = RetryConfig::forMarketplace('discogs');
echo "   Discogs marketplace: maxAttempts={$retryConfig->maxAttempts}, maxDelay={$retryConfig->maxDelay}s\n\n";

// Example 2: With rate limiter (if available)
echo "2. With RateLimiter integration:\n";

if (class_exists('Four\RateLimit\RateLimiterFactory')) {
    // Create rate limiter factory
    $limiterFactory = new \Four\RateLimit\RateLimiterFactory();
    
    // Token bucket: 20 requests per second with burst
    $limiter = $limiterFactory->create('api_requests', new \Four\RateLimit\Policy\TokenBucketPolicy(
        capacity: 20,
        refillRate: 20,
        refillPeriod: 1.0
    ));
    
    $config = ClientConfig::create('https://api.example.com')
        ->withRateLimit($limiter)
        ->withRetries(RetryConfig::default())
        ->build();
    
    echo "   Rate limiter: TokenBucket (capacity=20, refill=20/s)\n";
    echo "   Middleware: " . implode(', ', $config->middleware) . "\n";
    
} else {
    // Fallback without actual rate limiter
    $config = ClientConfig::create('https://api.example.com')
        ->withRetries(RetryConfig::conservative())
        ->build();
    
    echo "   Fallback: using retry config with conservative settings\n";
    echo "   Retry: maxAttempts=2, initialDelay=2.0s\n";
}

echo "\n";

// Example 3: Rate limit handling in error handling
echo "3. Error handling for rate limits:\n";

echo "   When 429 response received:\n";
echo "   - RetryMiddleware respects Retry-After header\n";
echo "   - Exponential backoff: 1s, 2s, 4s, ...\n";
echo "   - Max delay: configured per marketplace\n\n";

// Example 4: Marketplace-specific rate limits
echo "4. Marketplace rate limit headers:\n";

$marketplaces = [
    'amazon' => ['header' => 'x-amzn-ratelimit-remaining', 'typical' => 'varies by operation'],
    'ebay'   => ['header' => 'X-RateLimit-remaining', 'typical' => '5000/day'],
    'discogs'=> ['header' => 'X-Discogs-Ratelimit-Remaining', 'typical' => '60/minute'],
];

foreach ($marketplaces as $name => $info) {
    echo "   {$name}: {$info['header']} ({$info['typical']})\n";
}

echo "\n";

echo str_repeat('=', 40) . "\n";
echo "Rate limiting demo completed.\n";
