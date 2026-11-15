<?php

declare(strict_types=1);

namespace Four\Http\Tests\Client;

use Four\Http\Configuration\ClientConfig;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiClientTest extends TestCase
{
    private function client(string $baseUri, MockClient $http): TestApiClient
    {
        $factory = new Psr17Factory();

        return new TestApiClient(ClientConfig::create($baseUri)->build(), $http, $factory, $factory);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function urls(): iterable
    {
        yield 'relative with slash' => ['https://api.example.com', '/users/42', 'https://api.example.com/users/42'];
        yield 'relative without slash' => ['https://api.example.com/', 'users/42', 'https://api.example.com/users/42'];
        yield 'base with path' => ['https://api.example.com/v1', '/users', 'https://api.example.com/v1/users'];
        yield 'absolute stays' => ['https://api.example.com', 'https://other.example.org/x', 'https://other.example.org/x'];
        yield 'empty base' => ['', '/users', '/users'];
    }

    #[DataProvider('urls')]
    public function testResolvesUrlAgainstBaseUri(string $baseUri, string $url, string $expected): void
    {
        $http = new MockClient();
        $http->addResponse(new Response(200, [], '{"ok":true}'));

        self::assertSame(['ok' => true], $this->client($baseUri, $http)->get($url));

        $request = $http->getLastRequest();
        self::assertNotFalse($request);
        self::assertSame($expected, (string) $request->getUri());
    }

    public function testInvalidJsonThrowsWithoutWritingFiles(): void
    {
        $before = glob(sys_get_temp_dir() . '/four-http-jsonerr-*') ?: [];
        $http = new MockClient();
        $http->addResponse(new Response(200, ['Content-Type' => 'text/html'], '<html>Maintenance</html>'));

        try {
            $this->client('https://api.example.com', $http)->get('/users/42');
            self::fail('HttpClientException expected');
        } catch (\Four\Http\Exception\HttpClientException $e) {
            self::assertStringContainsString('JSON decode failed', $e->getMessage());
            self::assertStringContainsString('Content-Type: text/html', $e->getMessage());
            self::assertStringNotContainsString('dump', $e->getMessage());
            self::assertSame(200, $e->getCode());
        }

        self::assertSame($before, glob(sys_get_temp_dir() . '/four-http-jsonerr-*') ?: []);
    }
}
