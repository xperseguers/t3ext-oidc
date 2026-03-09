<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit;

use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\OAuthService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AbstractUnitTestBase extends UnitTestCase
{
    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    protected function createOAuthService(): OAuthService
    {
        return new OAuthService(
            self::createStub(EventDispatcherInterface::class),
            $this->setupOidcConfiguration()
        );
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    protected function setupOidcConfiguration(): OidcConfiguration
    {
        return new OidcConfiguration(
            [
                'enableFrontendAuthentication' => 0,
                'reEnableFrontendUsers' => 0,
                'undeleteFrontendUsers' => 0,
                'frontendUserMustExistLocally' => 0,
                'enableCodeVerifier' => 0,
                'enablePasswordCredentials' => 0,
                'usersStoragePid' => 0,
                'usersDefaultGroup' => '',
                'redirectUri' => '',
                'clientKey' => '',
                'clientSecret' => '',
                'clientScopes' => 'openid',
                'clientScopeSeparator' => '',
                'endpointAuthorize' => '',
                'endpointToken' => '',
                'endpointUserInfo' => '',
                'endpointLogout' => '',
                'endpointRevoke' => '',
                'authorizeLanguageParameter' => 'language',
                'useRequestPathAuthentication' => 0,
                'revokeAccessTokenAfterLogin' => 0,
                'disableCSRFProtection' => 0,
                'oauthProviderFactory' => '',
                'authenticationServicePriority' => 82,
                'authenticationServiceQuality' => 80,
                'authenticationUrlRoute' => 'oidc/authentication',
            ]
        );
    }
}
