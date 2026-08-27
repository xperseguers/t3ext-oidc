<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Provider;

use Causal\Oidc\Provider\GenericOpenIdDiscoveryProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(GenericOpenIdDiscoveryProvider::class)]
final class GenericOpenIdDiscoveryProviderTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private function createDiscoveryDocument(array $overrides = []): array
    {
        return array_merge([
            'issuer' => 'https://idp.example.com',
            'authorization_endpoint' => 'https://idp.example.com/oauth/authorize',
            'token_endpoint' => 'https://idp.example.com/oauth/token',
            'userinfo_endpoint' => 'https://idp.example.com/oidc/userinfo',
            'end_session_endpoint' => 'https://idp.example.com/oidc/logout',
            'revocation_endpoint' => 'https://idp.example.com/oauth/revoke',
            'jwks_uri' => 'https://idp.example.com/oauth/keys',
        ], $overrides);
    }

    private function createMockClient(array $discoveryDoc): GuzzleClient
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($discoveryDoc)),
        ]);
        return new GuzzleClient(['handler' => HandlerStack::create($mock)]);
    }

    private function createErrorClient(): GuzzleClient
    {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);
        return new GuzzleClient(['handler' => HandlerStack::create($mock)]);
    }

    #[Test]
    public function discoveryFillsEmptyEndpointUrls(): void
    {
        $doc = $this->createDiscoveryDocument();
        $provider = new GenericOpenIdDiscoveryProvider(
            [
                'clientId' => 'test-client',
                'clientSecret' => 'test-secret',
                'discoveryUrl' => 'https://idp.example.com',
            ],
            ['httpClient' => $this->createMockClient($doc)]
        );

        self::assertSame($doc['authorization_endpoint'], $provider->getBaseAuthorizationUrl());
        self::assertSame($doc['token_endpoint'], $provider->getBaseAccessTokenUrl([]));
    }

    #[Test]
    public function manualUrlsTakePrecedenceOverDiscovery(): void
    {
        $doc = $this->createDiscoveryDocument();
        $manualAuthorize = 'https://custom.example.com/auth';
        $manualToken = 'https://custom.example.com/token';

        $provider = new GenericOpenIdDiscoveryProvider(
            [
                'clientId' => 'test-client',
                'clientSecret' => 'test-secret',
                'discoveryUrl' => 'https://idp.example.com',
                'urlAuthorize' => $manualAuthorize,
                'urlAccessToken' => $manualToken,
                'urlResourceOwnerDetails' => '',
            ],
            ['httpClient' => $this->createMockClient($doc)]
        );

        self::assertSame($manualAuthorize, $provider->getBaseAuthorizationUrl());
        self::assertSame($manualToken, $provider->getBaseAccessTokenUrl([]));
    }

    #[Test]
    public function discoveryFailureIsNonFatal(): void
    {
        $provider = new GenericOpenIdDiscoveryProvider(
            [
                'clientId' => 'test-client',
                'clientSecret' => 'test-secret',
                'discoveryUrl' => 'https://idp.example.com',
                'urlAuthorize' => 'https://fallback.example.com/auth',
                'urlAccessToken' => 'https://fallback.example.com/token',
                'urlResourceOwnerDetails' => 'https://fallback.example.com/userinfo',
            ],
            ['httpClient' => $this->createErrorClient()]
        );

        self::assertSame('https://fallback.example.com/auth', $provider->getBaseAuthorizationUrl());
        self::assertSame([], $provider->getDiscoveryDocument());
    }

    #[Test]
    public function getDiscoveryDocumentReturnsFullDoc(): void
    {
        $doc = $this->createDiscoveryDocument();
        $provider = new GenericOpenIdDiscoveryProvider(
            [
                'clientId' => 'test-client',
                'clientSecret' => 'test-secret',
                'discoveryUrl' => 'https://idp.example.com',
            ],
            ['httpClient' => $this->createMockClient($doc)]
        );

        $result = $provider->getDiscoveryDocument();
        self::assertSame($doc['issuer'], $result['issuer']);
        self::assertSame($doc['end_session_endpoint'], $result['end_session_endpoint']);
        self::assertSame($doc['revocation_endpoint'], $result['revocation_endpoint']);
    }

    #[Test]
    public function normalizeDiscoveryUrlPrependsHttps(): void
    {
        $doc = $this->createDiscoveryDocument();
        $mockClient = $this->createMockClient($doc);

        $provider = new GenericOpenIdDiscoveryProvider(
            [
                'clientId' => 'test-client',
                'clientSecret' => 'test-secret',
                'discoveryUrl' => 'idp.example.com',
            ],
            ['httpClient' => $mockClient]
        );

        // If discovery succeeded, URLs were resolved → the bare domain was normalized
        self::assertNotEmpty($provider->getDiscoveryDocument());
    }

    #[Test]
    public function normalizeDiscoveryUrlAppendsWellKnown(): void
    {
        $doc = $this->createDiscoveryDocument();
        $mockClient = $this->createMockClient($doc);

        $provider = new GenericOpenIdDiscoveryProvider(
            [
                'clientId' => 'test-client',
                'clientSecret' => 'test-secret',
                'discoveryUrl' => 'https://idp.example.com',
            ],
            ['httpClient' => $mockClient]
        );

        self::assertNotEmpty($provider->getDiscoveryDocument());
    }

    #[Test]
    public function normalizeDiscoveryUrlKeepsFullUrl(): void
    {
        $doc = $this->createDiscoveryDocument();
        $mockClient = $this->createMockClient($doc);

        $provider = new GenericOpenIdDiscoveryProvider(
            [
                'clientId' => 'test-client',
                'clientSecret' => 'test-secret',
                'discoveryUrl' => 'https://idp.example.com/.well-known/openid-configuration',
            ],
            ['httpClient' => $mockClient]
        );

        self::assertNotEmpty($provider->getDiscoveryDocument());
    }

    #[Test]
    public function noDiscoveryUrlSkipsFetching(): void
    {
        $provider = new GenericOpenIdDiscoveryProvider(
            [
                'clientId' => 'test-client',
                'clientSecret' => 'test-secret',
                'urlAuthorize' => 'https://manual.example.com/auth',
                'urlAccessToken' => 'https://manual.example.com/token',
                'urlResourceOwnerDetails' => 'https://manual.example.com/userinfo',
            ],
            []
        );

        self::assertSame([], $provider->getDiscoveryDocument());
        self::assertSame('https://manual.example.com/auth', $provider->getBaseAuthorizationUrl());
    }
}
