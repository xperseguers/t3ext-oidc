<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Service;

use Causal\Oidc\AuthenticationContext;
use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\AuthenticationContextService;
use Causal\Oidc\Service\OAuthService;
use Causal\Oidc\Service\OpenIdConnectService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class OpenIdConnectServiceTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    #[DataProvider('getFinalLoginUrlReturnsExpectedUrlDataProvider')]
    public function getFinalLoginUrlReturnsExpectedUrl(string $loginUrl, string $expected): void
    {
        $this->setupOidcConfiguration();

        $service = new OpenIdConnectService(
            $this->createOAuthService(),
            new AuthenticationContextService(),
            new OidcConfiguration(),
        );

        $authenticationContext = new AuthenticationContext('', $loginUrl, '', '', false);
        self::assertSame($expected, (string)$service->getFinalLoginUrl($authenticationContext, 'somecode'));
    }

    public static function getFinalLoginUrlReturnsExpectedUrlDataProvider(): array
    {
        return [
            'default' => [
                'loginUrl' => 'https://example.com/login?logintype=login',
                'expected' => 'https://example.com/login?logintype=login&tx_oidc%5Bcode%5D=somecode',
            ],
            'preserves params' => [
                'loginUrl' => 'https://example.com/login?logintype=login&otherparam=foo',
                'expected' => 'https://example.com/login?logintype=login&otherparam=foo&tx_oidc%5Bcode%5D=somecode',
            ],
            'preserves redirect_url' => [
                'loginUrl' => 'https://example.com/login?logintype=login&redirect_url=http%3A%2F%2Fexample.com%2Fother',
                'expected' => 'https://example.com/login?logintype=login&redirect_url=http%3A%2F%2Fexample.com%2Fother&tx_oidc%5Bcode%5D=somecode',
            ],
        ];
    }

    private function createOAuthService(): OAuthService
    {
        $this->setupOidcConfiguration();

        return new OAuthService(
            self::createStub(EventDispatcherInterface::class),
            new OidcConfiguration(),
        );
    }

    private function setupOidcConfiguration(): void
    {
        $extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
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
    }
}
