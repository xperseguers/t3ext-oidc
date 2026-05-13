<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Service;

use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\AuthenticationContextService;
use Causal\Oidc\Service\OAuthService;
use Causal\Oidc\Service\OpenIdConnectService;
use Causal\Oidc\Tests\Unit\AbstractUnitTestBase;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;

#[CoversClass(OpenIdConnectService::class)]
#[CoversClass(OAuthService::class)]
final class PkcePublicClientTest extends AbstractUnitTestBase
{
    protected bool $resetSingletonInstances = true;

    private function createOidcConfig(array $overrides = []): OidcConfiguration
    {
        $defaults = [
            'enableFrontendAuthentication' => 0,
            'reEnableFrontendUsers' => 0,
            'undeleteFrontendUsers' => 0,
            'frontendUserMustExistLocally' => 0,
            'enableCodeVerifier' => 0,
            'enablePasswordCredentials' => 0,
            'usersStoragePid' => 0,
            'usersDefaultGroup' => '',
            'oidcRedirectUri' => '',
            'oidcClientKey' => 'test-client',
            'oidcClientSecret' => 'test-secret',
            'oidcClientScopes' => 'openid',
            'oidcClientScopeSeparator' => '',
            'oidcEndpointAuthorize' => 'https://idp.example.com/authorize',
            'oidcEndpointToken' => 'https://idp.example.com/token',
            'oidcEndpointUserInfo' => 'https://idp.example.com/userinfo',
            'oidcEndpointLogout' => '',
            'oidcEndpointRevoke' => 'https://idp.example.com/revoke',
            'oidcAuthorizeLanguageParameter' => 'language',
            'oidcUseRequestPathAuthentication' => 0,
            'oidcRevokeAccessTokenAfterLogin' => 0,
            'oidcDisableCSRFProtection' => 0,
            'oauthProviderFactory' => '',
            'authenticationServicePriority' => 82,
            'authenticationServiceQuality' => 80,
            'authenticationUrlRoute' => 'oidc/authentication',
        ];

        return new OidcConfiguration(array_merge($defaults, $overrides));
    }

    #[Test]
    public function publicClientSkipsSecretValidation(): void
    {
        $config = $this->createOidcConfig([
            'enableCodeVerifier' => 1,
            'oidcClientSecret' => '',
        ]);

        $service = new OpenIdConnectService(
            new OAuthService(self::createStub(EventDispatcherInterface::class), $config),
            new AuthenticationContextService(),
            $config,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([
            'login_url' => 'https://example.com/login',
        ]);

        // Should throw because of invalid login_url hash, NOT because of missing config.
        // Code 1715775147 = missing config, code 1759845557572 = invalid login_url,
        // code 1719003567 = invalid hash
        try {
            $service->generateAuthenticationContext($request);
            self::fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            self::assertNotSame(
                1715775147,
                $e->getCode(),
                'Public client should not fail on missing clientSecret validation'
            );
        }
    }

    #[Test]
    public function confidentialClientRequiresSecret(): void
    {
        $config = $this->createOidcConfig([
            'enableCodeVerifier' => 0,
            'oidcClientSecret' => '',
        ]);

        $service = new OpenIdConnectService(
            new OAuthService(self::createStub(EventDispatcherInterface::class), $config),
            new AuthenticationContextService(),
            $config,
        );

        $request = $this->createMock(ServerRequestInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1715775147);

        $service->generateAuthenticationContext($request);
    }

    #[Test]
    public function publicClientStillRequiresClientKey(): void
    {
        $config = $this->createOidcConfig([
            'enableCodeVerifier' => 1,
            'oidcClientKey' => '',
            'oidcClientSecret' => '',
        ]);

        $service = new OpenIdConnectService(
            new OAuthService(self::createStub(EventDispatcherInterface::class), $config),
            new AuthenticationContextService(),
            $config,
        );

        $request = $this->createMock(ServerRequestInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1715775147);

        $service->generateAuthenticationContext($request);
    }

    #[Test]
    public function revokeTokenUsesBasicAuthForConfidentialClient(): void
    {
        $config = $this->createOidcConfig([
            'oidcClientKey' => 'my-client',
            'oidcClientSecret' => 'my-secret',
        ]);

        $oauthService = new OAuthService(
            self::createStub(EventDispatcherInterface::class),
            $config,
        );

        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects(self::once())
            ->method('getRequest')
            ->with(
                'POST',
                'https://idp.example.com/revoke',
                self::callback(function (array $options) {
                    // Confidential client: should have Authorization header
                    self::assertArrayHasKey('Authorization', $options['headers']);
                    self::assertStringStartsWith('Basic ', $options['headers']['Authorization']);
                    // Body should NOT contain client_id
                    self::assertStringNotContainsString('client_id', $options['body']);
                    return true;
                })
            )
            ->willReturn(self::createStub(RequestInterface::class));

        $provider->method('getParsedResponse')->willReturn([]);

        $this->setProviderOnService($oauthService, $provider);

        $token = new AccessToken(['access_token' => 'test-token', 'expires' => time() + 3600]);
        self::assertTrue($oauthService->revokeToken($token));
    }

    #[Test]
    public function revokeTokenUsesClientIdInBodyForPublicClient(): void
    {
        $config = $this->createOidcConfig([
            'oidcClientKey' => 'my-public-client',
            'oidcClientSecret' => '',
        ]);

        $oauthService = new OAuthService(
            self::createStub(EventDispatcherInterface::class),
            $config,
        );

        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects(self::once())
            ->method('getRequest')
            ->with(
                'POST',
                'https://idp.example.com/revoke',
                self::callback(function (array $options) {
                    // Public client: should NOT have Authorization header
                    self::assertArrayNotHasKey('Authorization', $options['headers']);
                    // Body should contain client_id
                    self::assertStringContainsString('client_id=my-public-client', $options['body']);
                    return true;
                })
            )
            ->willReturn(self::createStub(RequestInterface::class));

        $provider->method('getParsedResponse')->willReturn([]);

        $this->setProviderOnService($oauthService, $provider);

        $token = new AccessToken(['access_token' => 'test-token', 'expires' => time() + 3600]);
        self::assertTrue($oauthService->revokeToken($token));
    }

    private function setProviderOnService(OAuthService $service, AbstractProvider $provider): void
    {
        $reflection = new ReflectionProperty($service, 'provider');
        $reflection->setValue($service, $provider);
    }
}
