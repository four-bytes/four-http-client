# Changelog — four-http-client


## [4.0.0] — 2026-02-25

### Breaking Changes
- `MarketplaceHttpClientFactory` → `HttpClientFactory` (renamed)
- `MarketplaceClient` → `ApiClient` (renamed)
- Removed context string parameter from exceptions and middleware
- `RetryConfig::forMarketplace()` removed
- `symfony/http-client` removed from dependencies — `php-http/discovery` takes over
- `symfony/cache` removed from dependencies
- `psr/cache` removed from `require` (no cache in Factory/Config)
- `$cache` parameter removed from `HttpClientFactory` + `ClientConfig`

### Added
- `DiscoveryHttpTransport` — uses `php-http/discovery` for PSR-18 transport (Symfony-free)
- `Psr7HttpResponse` — PSR-7 response adapter for `HttpResponseInterface`
- `php-http/mock-client` in `require-dev` for tests

### Architecture
- Library is now completely Symfony-free (no Symfony in require or require-dev)

## [3.0.0] — 2026-02-25

### Breaking Changes
- Symfony completely removed from public API — optional internal implementation only
- `symfony/rate-limiter` and `symfony/cache` removed from `require`
- `RateLimiterFactory` (Symfony) in `ClientConfig` + `ClientConfigBuilder` replaced by `Four\RateLimiting\RateLimiterInterface`
- `createRateLimiterFactory()` in `MarketplaceHttpClientFactory` removed
- `MiddlewareInterface::wrap()` now takes `HttpTransportInterface` instead of `Symfony\HttpClientInterface`
- `ClientConfig::$rateLimiterFactory` → `$rateLimiter` (type: `RateLimiterInterface`)
- `ClientConfigBuilder::withRateLimitPolicy()` removed — use `withRateLimit($limiter)` directly

### Added
- Own `Four\Http\Transport\HttpTransportInterface` — decouples middleware from Symfony stack
- `SymfonyHttpTransport` + `SymfonyHttpResponse` — Symfony as optional implementation
- `TransportPsr18Adapter` + `TransportPsr7Response` — PSR-18 bridge over transport layer
- `four-bytes/four-rate-limiting` as dependency
- `symfony/http-client` in `suggest` (optional default transport)

### Fixed
- `OAuth1aProvider`: port null-coalescing (`$parsedUrl['port'] ?? null`)
- Tests: `assertStringContains` → `assertStringContainsString` (PHPUnit 11 compatibility)

## [2.0.0] — 2026-02-25

- **Breaking**: Removed API-specific client presets
- **Breaking**: `HttpClientFactoryInterface::createClient()` → `create()`, returns PSR-18 `ClientInterface`
- **Breaking**: `MarketplaceClient` uses PSR-18 instead of Symfony `HttpClientInterface`
- **Changed**: `MarketplaceHttpClientFactory` returns PSR-18 `ClientInterface`
- **Changed**: Removed API-specific factory methods
- **Changed**: `MarketplaceClient` migrated to PSR-7/PSR-18
- **Added**: `NotFoundException` for HTTP 404 responses
- **Added**: `psr/http-factory: ^1.0`, `php-http/discovery: ^1.19`
- **Fixed**: `OAuth1aProvider::getExpiresAt()` missing interface method
- **Fixed**: `LoggedResponse::getInfo()` return type for PHP 8.4

## [1.0.0] — 2026-02-25

- Initial release
