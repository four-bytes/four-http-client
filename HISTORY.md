# Changelog — four-marketplaces-http-client

## [3.0.1] — 2026-02-25

### Breaking Changes
- `symfony/rate-limiter` und `symfony/cache` aus `require` entfernt
- `RateLimiterFactory` (Symfony) in `ClientConfig` + `ClientConfigBuilder` ersetzt durch `Four\RateLimiting\RateLimiterInterface`
- `createRateLimiterFactory()` in `MarketplaceHttpClientFactory` entfernt
- `MiddlewareInterface::wrap()` nimmt jetzt `HttpTransportInterface` statt `Symfony\HttpClientInterface`

### Neu
- Eigenes `Four\Http\Transport\HttpTransportInterface` — entkoppelt Middleware vom Symfony-Stack
- `SymfonyHttpTransport` + `SymfonyHttpResponse` — Symfony als optionale Implementierung
- `TransportPsr18Adapter` + `TransportPsr7Response` — PSR-18 Brücke über Transport-Layer
- `four-bytes/four-rate-limiting` als Dependency (via path-repository)
- `symfony/http-client` in `require-dev` + `suggest` (optionaler Standard-Transport)

### Fixes
- `OAuth1aProvider`: Port-Null-Coalescing (`$parsedUrl['port'] ?? null`)
- Tests: `assertStringContains` → `assertStringContainsString` (PHPUnit 11 Kompatibilität)


## [2.0.0] - 2026-02-25
- **Breaking**: Entfernt marketplace-spezifische Clients (Amazon, eBay, Bandcamp, Discogs)
- **Breaking**: `HttpClientFactoryInterface::createClient()` → `create()`, gibt PSR-18 `ClientInterface` zurück
- **Breaking**: `MarketplaceClient` nutzt PSR-18 statt Symfony `HttpClientInterface`
- **Changed**: `MarketplaceHttpClientFactory` gibt PSR-18 `ClientInterface` zurück statt Symfony `HttpClientInterface`
- **Changed**: Factory-Methoden `createAmazonClient()`, `createEbayClient()`, `createDiscogsClient()`, `createBandcampClient()` entfernt
- **Changed**: `MarketplaceClient` auf PSR-7/PSR-18 umgestellt (Request/StreamFactory als Konstruktor-Dependencies)
- **Added**: `NotFoundException` für HTTP 404-Responses
- **Added**: `psr/http-factory: ^1.0` als Dependency
- **Added**: `php-http/discovery: ^1.19` für automatische PSR-18/PSR-17 Implementierungserkennung
- **Fixed**: `OAuth1aProvider::getExpiresAt()` fehlende Interface-Methode implementiert
- **Fixed**: `LoggedResponse::getInfo()` Return-Type für PHP 8.4 Kompatibilität

## [3.0.0] - 2026-02-25
- **Breaking**: Symfony komplett aus Public API entfernt — nur noch optionale interne Implementierung
- **Added**: `Four\Http\Transport\HttpTransportInterface` — eigenes Transport-Interface
- **Added**: `Four\Http\Transport\HttpResponseInterface` — eigenes Response-Interface
- **Added**: `Four\Http\Transport\SymfonyHttpTransport` — Symfony-Adapter für HttpTransportInterface
- **Added**: `Four\Http\Transport\SymfonyHttpResponse` — Symfony-Adapter für HttpResponseInterface
- **Added**: `Four\Http\Transport\TransportPsr18Adapter` — PSR-18 Adapter über HttpTransportInterface
- **Added**: `Four\Http\Transport\TransportPsr7Response` — PSR-7 Response-Wrapper via nyholm/psr7
- **Breaking**: `MiddlewareInterface::wrap()` nimmt jetzt `HttpTransportInterface` statt `HttpClientInterface`
- **Breaking**: `RateLimitingMiddleware` nutzt jetzt `four-bytes/four-rate-limiting` statt `symfony/rate-limiter`
- **Breaking**: `ClientConfig::$rateLimiterFactory` → `$rateLimiter` (Typ: `Four\RateLimit\RateLimiterInterface`)
- **Breaking**: `ClientConfigBuilder::withRateLimit()` nimmt jetzt `?RateLimiterInterface` statt `?RateLimiterFactory`
- **Removed**: `ClientConfigBuilder::withRateLimitPolicy()` — direkt `withRateLimit($limiter)` nutzen
- **Removed**: `MarketplaceHttpClientFactory::createRateLimiterFactory()` — symfony/rate-limiter entfernt
- **Removed**: `symfony/rate-limiter` und `symfony/cache` aus require (jetzt nur noch require-dev)
- **Added**: `four-bytes/four-rate-limiting` als require-Dependency
