# Changelog — four-http-client

## [3.0.0] — 2026-02-25

### Breaking Changes
- Symfony komplett aus Public API entfernt — nur noch optionale interne Implementierung
- `symfony/rate-limiter` und `symfony/cache` aus `require` entfernt
- `RateLimiterFactory` (Symfony) in `ClientConfig` + `ClientConfigBuilder` ersetzt durch `Four\RateLimiting\RateLimiterInterface`
- `createRateLimiterFactory()` in `MarketplaceHttpClientFactory` entfernt
- `MiddlewareInterface::wrap()` nimmt jetzt `HttpTransportInterface` statt `Symfony\HttpClientInterface`
- `ClientConfig::$rateLimiterFactory` → `$rateLimiter` (Typ: `RateLimiterInterface`)
- `ClientConfigBuilder::withRateLimitPolicy()` entfernt — direkt `withRateLimit($limiter)` nutzen

### Neu
- Eigenes `Four\Http\Transport\HttpTransportInterface` — entkoppelt Middleware vom Symfony-Stack
- `SymfonyHttpTransport` + `SymfonyHttpResponse` — Symfony als optionale Implementierung
- `TransportPsr18Adapter` + `TransportPsr7Response` — PSR-18 Brücke über Transport-Layer
- `four-bytes/four-rate-limiting` als Dependency
- `symfony/http-client` in `suggest` (optionaler Standard-Transport)

### Fixes
- `OAuth1aProvider`: Port-Null-Coalescing (`$parsedUrl['port'] ?? null`)
- Tests: `assertStringContains` → `assertStringContainsString` (PHPUnit 11 Kompatibilität)

## [2.0.0] — 2026-02-25

- **Breaking**: Entfernt marketplace-spezifische Clients (Amazon, eBay, Bandcamp, Discogs)
- **Breaking**: `HttpClientFactoryInterface::createClient()` → `create()`, gibt PSR-18 `ClientInterface` zurück
- **Breaking**: `MarketplaceClient` nutzt PSR-18 statt Symfony `HttpClientInterface`
- **Changed**: `MarketplaceHttpClientFactory` gibt PSR-18 `ClientInterface` zurück
- **Changed**: Factory-Methoden `createAmazonClient()` etc. entfernt
- **Changed**: `MarketplaceClient` auf PSR-7/PSR-18 umgestellt
- **Added**: `NotFoundException` für HTTP 404-Responses
- **Added**: `psr/http-factory: ^1.0`, `php-http/discovery: ^1.19`
- **Fixed**: `OAuth1aProvider::getExpiresAt()` fehlende Interface-Methode
- **Fixed**: `LoggedResponse::getInfo()` Return-Type für PHP 8.4

## [1.0.0] — 2026-02-25

- Initial release
