# Changelog — four-marketplaces-http-client

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
