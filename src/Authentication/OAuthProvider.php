<?php

declare(strict_types=1);

namespace Four\Http\Authentication;

use Four\Http\Exception\AuthenticationException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Authentication provider for OAuth 2.0 token flows
 *
 * Handles token management, refresh (refresh_token or client_credentials grant)
 * and header generation. Platform-specific settings such as the token endpoint
 * or the client authentication method belong to the API client using it.
 */
class OAuthProvider implements AuthProviderInterface
{
    private ?string $accessToken = null;
    private ?\DateTimeInterface $expiresAt = null;

    /**
     * @param array<string> $scopes
     */
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $tokenEndpoint,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?string $refreshToken = null,
        private readonly array $scopes = [],
        private readonly string $oauthVersion = '2.0',
        private readonly ClientAuthMethod $clientAuthMethod = ClientAuthMethod::ClientSecretPost,
    ) {}

    public function getAuthHeaders(): array
    {
        if (!$this->isValid()) {
            $this->refresh();
        }

        if ($this->accessToken === null) {
            throw AuthenticationException::missing();
        }

        return [
            'Authorization' => "Bearer {$this->accessToken}"
        ];
    }

    public function isValid(): bool
    {
        if ($this->accessToken === null) {
            return false;
        }

        if ($this->expiresAt !== null) {
            // Add 30 seconds buffer to avoid edge cases
            $bufferTime = (new \DateTimeImmutable())->add(new \DateInterval('PT30S'));
            return $this->expiresAt > $bufferTime;
        }

        return true;
    }

    public function refresh(): void
    {
        if ($this->oauthVersion === '2.0') {
            $this->refreshOAuth2Token();
        } else {
            throw new \BadMethodCallException('OAuth 1.0a refresh not implemented');
        }
    }

    public function getType(): string
    {
        return "oauth_{$this->oauthVersion}";
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    /**
     * Set access token directly (for when you have a valid token)
     */
    public function setAccessToken(string $accessToken, ?\DateTimeInterface $expiresAt = null): void
    {
        $this->accessToken = $accessToken;
        $this->expiresAt = $expiresAt;
    }

    /**
     * Refresh OAuth 2.0 token using refresh token or client credentials
     */
    private function refreshOAuth2Token(): void
    {
        $grantType = $this->refreshToken ? 'refresh_token' : 'client_credentials';

        $params = ['grant_type' => $grantType];

        if ($this->clientAuthMethod === ClientAuthMethod::ClientSecretPost) {
            $params['client_id'] = $this->clientId;
            $params['client_secret'] = $this->clientSecret;
        }

        if ($this->refreshToken) {
            $params['refresh_token'] = $this->refreshToken;
        }

        if (!empty($this->scopes)) {
            $params['scope'] = implode(' ', $this->scopes);
        }

        $body = $this->streamFactory->createStream(http_build_query($params));

        $request = $this->requestFactory
            ->createRequest('POST', $this->tokenEndpoint)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($body);

        if ($this->clientAuthMethod === ClientAuthMethod::ClientSecretBasic) {
            // RFC 6749 §2.3.1: both values form-urlencoded before Base64
            $credentials = urlencode($this->clientId) . ':' . urlencode($this->clientSecret);
            $request = $request->withHeader('Authorization', 'Basic ' . base64_encode($credentials));
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new AuthenticationException('Failed to refresh OAuth token: ' . $e->getMessage(), 401, $e);
        }

        $content = (string) $response->getBody();

        if ($response->getStatusCode() !== 200) {
            throw new AuthenticationException(
                'Failed to refresh OAuth token: HTTP ' . $response->getStatusCode() . self::describeTokenError($content),
                401,
                null,
                null,
                'credentials'
            );
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new AuthenticationException('Invalid JSON response from token endpoint', 401, $e);
        }

        if (!is_array($data) || !isset($data['access_token']) || !is_string($data['access_token'])) {
            throw new AuthenticationException('Token endpoint response contains no access_token', 401, null, null, 'credentials');
        }

        $this->accessToken = $data['access_token'];

        if (isset($data['expires_in'])) {
            $expiresIn = (int) $data['expires_in'];
            $this->expiresAt = (new \DateTimeImmutable())->add(new \DateInterval("PT{$expiresIn}S"));
        }
    }

    /**
     * " — error: error_description" from an RFC 6749 §5.2 error response, if present.
     * Only these two fields are used, so nothing from the request ends up in the message.
     */
    private static function describeTokenError(string $content): string
    {
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }

        if (!is_array($data) || !isset($data['error']) || !is_string($data['error'])) {
            return '';
        }

        $description = isset($data['error_description']) && is_string($data['error_description'])
            ? ': ' . $data['error_description']
            : '';

        return ' — ' . $data['error'] . $description;
    }

    /**
     * Get token information for debugging
     *
     * @return array<string, mixed>
     */
    public function getTokenInfo(): array
    {
        return [
            'type' => $this->getType(),
            'client_auth_method' => $this->clientAuthMethod->value,
            'has_access_token' => $this->accessToken !== null,
            'has_refresh_token' => $this->refreshToken !== null,
            'expires_at' => $this->expiresAt?->format('c'),
            'is_valid' => $this->isValid(),
            'scopes' => $this->scopes
        ];
    }
}
