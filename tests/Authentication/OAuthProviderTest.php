<?php

declare(strict_types=1);

namespace Four\Http\Tests\Authentication;

use Four\Http\Authentication\ClientAuthMethod;
use Four\Http\Authentication\OAuthProvider;
use Four\Http\Exception\AuthenticationException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class OAuthProviderTest extends TestCase
{
    private MockClient $client;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->client = new MockClient();
        $this->factory = new Psr17Factory();
    }

    private function provider(ClientAuthMethod $method = ClientAuthMethod::ClientSecretPost): OAuthProvider
    {
        return new OAuthProvider(
            'client id',
            'se:cret',
            'https://auth.example.com/token',
            $this->client,
            $this->factory,
            $this->factory,
            'refresh-token',
            ['scope-a', 'scope-b'],
            '2.0',
            $method,
        );
    }

    private function tokenResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"at-1","expires_in":3600}');
    }

    /** @return array<string, string> */
    private function sentBody(): array
    {
        $request = $this->client->getLastRequest();
        self::assertNotFalse($request);
        parse_str((string) $request->getBody(), $params);
        /** @var array<string, string> $params */
        return $params;
    }

    public function testClientSecretPostSendsCredentialsInBody(): void
    {
        $this->client->addResponse($this->tokenResponse());

        $headers = $this->provider()->getAuthHeaders();

        self::assertSame('Bearer at-1', $headers['Authorization']);
        $body = $this->sentBody();
        self::assertSame('refresh_token', $body['grant_type']);
        self::assertSame('client id', $body['client_id']);
        self::assertSame('se:cret', $body['client_secret']);
        self::assertSame('refresh-token', $body['refresh_token']);
        self::assertSame('scope-a scope-b', $body['scope']);

        $request = $this->client->getLastRequest();
        self::assertNotFalse($request);
        self::assertFalse($request->hasHeader('Authorization'));
    }

    public function testClientSecretBasicSendsCredentialsInHeaderOnly(): void
    {
        $this->client->addResponse($this->tokenResponse());

        $this->provider(ClientAuthMethod::ClientSecretBasic)->getAuthHeaders();

        $request = $this->client->getLastRequest();
        self::assertNotFalse($request);
        // RFC 6749 §2.3.1: form-urlencoded values, then Base64
        self::assertSame('Basic ' . base64_encode('client+id:se%3Acret'), $request->getHeaderLine('Authorization'));

        $body = $this->sentBody();
        self::assertArrayNotHasKey('client_id', $body);
        self::assertArrayNotHasKey('client_secret', $body);
        self::assertSame('refresh-token', $body['refresh_token']);
    }

    public function testErrorResponseIncludesErrorAndDescription(): void
    {
        $this->client->addResponse(new Response(401, [], '{"error":"invalid_client","error_description":"client authentication failed"}'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('HTTP 401 — invalid_client: client authentication failed');

        $this->provider()->getAuthHeaders();
    }

    public function testErrorMessageDoesNotContainSecrets(): void
    {
        $this->client->addResponse(new Response(400, [], 'plain text error'));

        try {
            $this->provider()->getAuthHeaders();
            self::fail('expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertStringNotContainsString('se:cret', $e->getMessage());
            self::assertSame('Failed to refresh OAuth token: HTTP 400', $e->getMessage());
        }
    }

    public function testMissingAccessTokenThrows(): void
    {
        $this->client->addResponse(new Response(200, [], '{"token_type":"bearer"}'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('no access_token');

        $this->provider()->getAuthHeaders();
    }

    public function testDefaultIsClientSecretPost(): void
    {
        $provider = new OAuthProvider('id', 'secret', 'https://auth.example.com/token', $this->client, $this->factory, $this->factory);

        self::assertSame('client_secret_post', $provider->getTokenInfo()['client_auth_method']);
    }
}
