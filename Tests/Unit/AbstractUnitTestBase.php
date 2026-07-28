<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit;

use Causal\Oidc\Exception\ExtensionNotConfiguredException;
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
     * @throws ExtensionNotConfiguredException
     */
    protected function setupOidcConfiguration(): OidcConfiguration
    {
        return new OidcConfiguration([
            'authenticationServicePriority' => 82,
            'authenticationServiceQuality' => 80,
            'authenticationUrlRoute' => 'oidc/authentication',
            'providers' => [
                'default' => [
                    'enableBackendAuthentication' => 1,
                    'enableFrontendAuthentication' => 1,
                    'reEnableFrontendUsers' => 0,
                    'undeleteFrontendUsers' => 0,
                    'frontendUserMustExistLocally' => 0,
                    'enableCodeVerifier' => 0,
                    'enablePasswordCredentials' => 0,
                    'usersStoragePid' => 0,
                    'usersDefaultGroup' => '',
                    'redirectUri' => '',
                    'clientKey' => 't3ext-oidc',
                    'clientSecret' => 't3ext-oidc',
                    'clientScopes' => 'openid',
                    'clientScopeSeparator' => '',
                    'endpointAuthorize' => 'http://oidc.t3ext-oidc.test/connect/authorize',
                    'endpointToken' => 'http://oidc.t3ext-oidc.test/connect/token',
                    'endpointUserInfo' => 'http://oidc.t3ext-oidc.test/connect/userinfo',
                    'endpointLogout' => '',
                    'endpointRevoke' => 'http://oidc.t3ext-oidc.test/connect/revocation',
                    'authorizeLanguageParameter' => 'language',
                    'useRequestPathAuthentication' => 0,
                    'revokeAccessTokenAfterLogin' => 0,
                    'disableCSRFProtection' => 0,
                    'oauthProviderFactory' => '',
                    'mapping' => [
                        'be_users' => [
                            'realName' => '<name>',
                        ],
                        'fe_users' => [
                            'name' => '<name>',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
