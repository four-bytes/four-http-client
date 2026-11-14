<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\Http\Configuration\RetryConfig;
use Four\Http\Exception\RateLimitException;

/**
 * Retry policies and rate-limit headers — runs offline.
 */

echo "Four HTTP Client — retries and rate limits\n\n";

// 1. Retry presets and their backoff
echo "1. Retry presets (delay per attempt):\n";
foreach (['default' => RetryConfig::default(), 'conservative' => RetryConfig::conservative(), 'aggressive' => RetryConfig::aggressive()] as $name => $retry) {
    $delays = array_map(static fn (int $n): string => sprintf('%.1fs', $retry->calculateDelay($n)), range(1, $retry->maxAttempts));
    printf("   %-12s max %d attempts: %s\n", $name, $retry->maxAttempts, implode(', ', $delays));
}

// 2. Custom policy
$custom = new RetryConfig(maxAttempts: 5, initialDelay: 0.5, multiplier: 3.0, maxDelay: 20.0);
echo "\n2. Custom policy retries 503: " . ($custom->shouldRetryStatusCode(503) ? 'yes' : 'no')
    . ", retries 400: " . ($custom->shouldRetryStatusCode(400) ? 'yes' : 'no') . "\n";

// 3. 429 responses: standard headers are read by default …
$e = RateLimitException::fromHeaders(['Retry-After' => '30', 'X-RateLimit-Remaining' => '0', 'X-RateLimit-Limit' => '100']);
echo "\n3. Standard headers: {$e->getMessage()}\n";

// … platform-specific header names are passed by the API client
$e = RateLimitException::fromHeaders(
    ['X-Platform-Remaining' => '0', 'X-Platform-Limit' => '60'],
    null,
    ['x-platform-remaining'],
    ['x-platform-limit'],
);
echo "   Custom headers:   {$e->getMessage()}\n";
