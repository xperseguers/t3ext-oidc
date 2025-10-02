<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Service;

use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\OAuthService;
use DateTimeImmutable;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[CoversClass(OAuthService::class)]
final class OAuthServiceTest extends TestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function getFreshAccessTokenReturnsExistingAccessTokenIfNotExpired(): void
    {
        $accessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('+30 seconds'));

        $subject = $this->createSubject();

        $result = $subject->getFreshAccessToken(json_encode($accessToken));

        self::assertSame(
            json_encode($accessToken),
            json_encode($result)
        );
    }

    #[Test]
    public function getFreshAccessTokenReturnsFreshAccessTokenIfExpired(): void
    {
        $accessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('-30 seconds'));
        $newAccessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('+1 minutes'));

        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects(self::once())
            ->method('getAccessToken')
            ->with(
                self::isInstanceOf(RefreshToken::class),
                [
                    'refresh_token' => 'refresh_token_value',
                ]
            )
            ->willReturn($newAccessToken);

        $subject = $this->createSubject();

        $this->setProperty($subject, 'provider', $provider);

        $result = $subject->getFreshAccessToken(json_encode($accessToken));

        self::assertSame(
            $newAccessToken,
            $result,
        );
    }

    #[Test]
    public function getFreshAccessTokenReturnsNullIfAccessTokenIsInvalid(): void
    {
        $subject = $this->createSubject();

        $result = $subject->getFreshAccessToken('');

        self::assertNull($result);
    }

    #[Test]
    public function getFreshAccessTokenReturnsNullIfRefreshThrowsIdentityProviderException(): void
    {
        $accessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('-30 seconds'));

        $provider = self::createStub(AbstractProvider::class);
        $provider->method('getAccessToken')->willThrowException(new IdentityProviderException('message', 10, 'response'));

        $subject = $this->createSubject();

        $this->setProperty($subject, 'provider', $provider);

        $result = $subject->getFreshAccessToken(json_encode($accessToken));

        self::assertNull($result);
    }

    private function createSubject(): OAuthService
    {
        $extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
            'enableBackendAuthentication' => 0,
            'enableFrontendAuthentication' => 0,
            'reEnableFrontendUsers' => 0,
            'undeleteFrontendUsers' => 0,
            'frontendUserMustExistLocally' => 0,
            'enableCodeVerifier' => 0,
            'enablePasswordCredentials' => 0,
            'usersStoragePid' => 0,
            'usersDefaultGroup' => '',
            'oidcRedirectUri' => '',
            'oidcClientKey' => '',
            'oidcClientSecret' => '',
            'oidcClientScopes' => 'openid',
            'oidcEndpointAuthorize' => '',
            'oidcEndpointToken' => '',
            'oidcEndpointUserInfo' => '',
            'oidcEndpointLogout' => '',
            'oidcEndpointRevoke' => '',
            'oidcAuthorizeLanguageParameter' => 'language',
            'oidcUseRequestPathAuthentication' => 0,
            'oidcRevokeAccessTokenAfterLogin' => 0,
            'oidcDisableCSRFProtection' => 0,
            'oauthProviderFactory' => '',
            'authenticationServicePriority' => 82,
            'authenticationServiceQuality' => 80,
            'authenticationUrlRoute' => 'oidc/authentication',
        ]);

        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfiguration);

        return new OAuthService(
            self::createStub(EventDispatcherInterface::class),
            new OidcConfiguration(),
        );
    }

    private function createAccessTokenWithExpire(DateTimeImmutable $expires): AccessToken
    {
        return new AccessToken([
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value',
            'expires' => (int)$expires->format('U'),
        ]);
    }

    private function setProperty(object $subject, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($subject, $property);
        $reflection->setValue($subject, $value);
    }
}
