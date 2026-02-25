# Changelog — four-http-client

## [4.0.0] — 2026-02-25

### Breaking Changes
- `MarketplaceHttpClientFactory` → `HttpClientFactory` (umbenennt)
- `MarketplaceClient` → `ApiClient` (umbenennt)
- `$marketplace`-Parameter aus allen Exceptions entfernt (`getMarketplace()`, `forMarketplace()`)
- `RetryConfig::forMarketplace()` entfernt
- `$marketplace`-Parameter aus `LoggingMiddleware` + `RetryMiddleware` entfernt
- `symfony/http-client` komplett aus Dependencies entfernt — `php-http/discovery` übernimmt
- `symfony/cache` komplett aus Dependencies entfernt
- `psr/cache` aus `require` entfernt (kein Cache mehr in Factory/Config)
- `$cache`-Parameter aus `HttpClientFactory` + `ClientConfig` entfernt

### Neu
- `DiscoveryHttpTransport` — nutzt `php-http/discovery` für PSR-18 Transport (Symfony-frei)
- `Psr7HttpResponse` — PSR-7 Response Adapter für `HttpResponseInterface`
- `php-http/mock-client` in `require-dev` für Tests

### Architektur
- Library ist jetzt vollständig Symfony-frei (kein Symfony in require oder require-dev)
- Marketplace-spezifische Logik ausgelagert → zukünftiges `four-marketplace-client` Repo

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
