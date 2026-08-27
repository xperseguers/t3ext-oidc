<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit;

use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\OAuthService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AbstractUnitTestBase extends UnitTestCase
{
    protected function createOAuthService(): OAuthService
    {
        $service = new OAuthService(
            self::createStub(EventDispatcherInterface::class),
            $this->setupOidcConfiguration()
        );
        $uri = new Uri('http://example.com/');
        $request = new ServerRequest(
            $uri,
            'GET',
            'php://input',
            [],
            [
                'HTTP_HOST' => $uri->getHost(),
                'SERVER_NAME' => $uri->getHost(),
                'HTTPS' => $uri->getScheme() === 'https',
                'SCRIPT_FILENAME' => __FILE__,
                'SCRIPT_NAME' => rtrim($uri->getPath(), '/') . '/',
            ]
        );
        $normalizedParams = NormalizedParams::createFromRequest($request);
        $request = $request->withAttribute('normalizedParams', $normalizedParams);
        $service->setRequest($request);
        return $service;
    }

    protected function setupOidcConfiguration(): OidcConfiguration
    {
        return new OidcConfiguration([
            'enableBackendAuthentication' => '0',
            'enableFrontendAuthentication' => '0',
            'reEnableFrontendUsers' => '0',
            'undeleteFrontendUsers' => '0',
            'frontendUserMustExistLocally' => '0',
            'enableCodeVerifier' => '0',
            'enablePasswordCredentials' => '0',
            'usersStoragePid' => '0',
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
            'oidcUseRequestPathAuthentication' => '0',
            'oidcRevokeAccessTokenAfterLogin' => '0',
            'oidcDisableCSRFProtection' => '0',
            'oauthProviderFactory' => '',
            'authenticationServicePriority' => '82',
            'authenticationServiceQuality' => '80',
            'authenticationUrlRoute' => 'oidc/authentication',
            'oidcDiscoveryUrl' => '',
        ]);
    }
}
