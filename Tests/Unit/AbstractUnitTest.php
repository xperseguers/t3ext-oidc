<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit;

use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\OAuthService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AbstractUnitTest extends UnitTestCase
{
    protected function setupOidcConfiguration(): void
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
            'oidcClientScopeSeparator' => '',
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

    protected function createOAuthService(): OAuthService
    {
        return new OAuthService(
            self::createStub(EventDispatcherInterface::class),
            new OidcConfiguration(),
        );
    }
}
