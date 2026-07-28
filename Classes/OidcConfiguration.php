<?php

declare(strict_types=1);

namespace Causal\Oidc;

use Causal\Oidc\Exception\ExtensionNotConfiguredException;
use Causal\Oidc\Model\Provider;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class OidcConfiguration
{
    public const CONFIG_PATH = '/system/oidc.yaml';

    public int $authenticationServicePriority = 82;
    public int $authenticationServiceQuality = 80;
    public string $authenticationUrlRoute = 'oidc/authentication';

    public string $authorizeLanguageParameter;
    public string $clientKey;
    public string $clientScopeSeparator;
    public string $clientScopes;
    public string $clientSecret;
    public bool $disableCSRFProtection;
    public bool $enableBackendAuthentication;
    public bool $enableCodeVerifier;
    public bool $enableFrontendAuthentication;
    public bool $enablePasswordCredentials;
    public string $endpointAuthorize;
    public string $endpointLogout;
    public string $endpointRevoke;
    public string $endpointToken;
    public string $endpointUserInfo;
    public bool $frontendUserMustExistLocally;
    public string $oauthProviderFactory;
    public bool $reEnableFrontendUsers;
    public string $redirectUri;
    public bool $revokeAccessTokenAfterLogin;
    public bool $undeleteFrontendUsers;
    public bool $useRequestPathAuthentication;
    public string $usersDefaultGroup;
    public array $usersStoragePids;

    /** @var array<Provider>  */
    private array $providers = [];

    /**
     * @param array<string, string> $extConfig
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionNotConfiguredException
     */
    public function __construct(?array $yamlConfig = null)
    {
        /** @var array{authenticationServicePriority: int, authenticationServiceQuality: int, authenticationUrlRoute: string, providers: array<string, Provider>} $yamlConfig */
        $yamlConfig ??= GeneralUtility::makeInstance(YamlFileLoader::class)
            ->load(Environment::getConfigPath() . self::CONFIG_PATH);

        if (isset($yamlConfig['authenticationServicePriority'])) {
            settype($yamlConfig['authenticationServicePriority'], gettype($this->authenticationServicePriority));
            $this->authenticationServicePriority = $yamlConfig['authenticationServicePriority'];
        }

        if (isset($yamlConfig['authenticationServiceQuality'])) {
            settype($yamlConfig['authenticationServiceQuality'], gettype($this->authenticationServiceQuality));
            $this->authenticationServiceQuality = $yamlConfig['authenticationServiceQuality'];
        }

        if (isset($yamlConfig['authenticationUrlRoute'])) {
            settype($yamlConfig['authenticationUrlRoute'], gettype($this->authenticationUrlRoute));
            $this->authenticationUrlRoute = $yamlConfig['authenticationUrlRoute'];
        }

        if (!count($yamlConfig['providers'])) {
            throw new ExtensionNotConfiguredException(
                'OIDC extension configuration does not contain any providers.',
                1773166983
            );
        }

        try {
            foreach ($yamlConfig['providers'] as $name => $provider) {
                $this->providers[] = new Provider($name, $provider);
                // Only one is currently support
                break;
            }

            $this->enableBackendAuthentication = $this->providers[0]->isEnableBackendAuthentication();
            $this->enableFrontendAuthentication = $this->providers[0]->isEnableFrontendAuthentication();
            $this->reEnableFrontendUsers = $this->providers[0]->isReEnableFrontendUsers();
            $this->undeleteFrontendUsers = $this->providers[0]->isUndeleteFrontendUsers();
            $this->frontendUserMustExistLocally = $this->providers[0]->isFrontendUserMustExistLocally();
            $this->disableCSRFProtection = $this->providers[0]->isDisableCSRFProtection();
            $this->enableCodeVerifier = $this->providers[0]->isEnableCodeVerifier();
            $this->authorizeLanguageParameter = $this->providers[0]->getAuthorizeLanguageParameter();
            $this->useRequestPathAuthentication = $this->providers[0]->isUseRequestPathAuthentication();
            $this->oauthProviderFactory = $this->providers[0]->getOauthProviderFactory();
            $this->clientKey = $this->providers[0]->getClientKey();
            $this->clientSecret = $this->providers[0]->getClientSecret();
            $this->clientScopes = $this->providers[0]->getClientScopes();
            $this->clientScopeSeparator = $this->providers[0]->getClientScopeSeparator();
            $this->endpointAuthorize = $this->providers[0]->getEndpointAuthorize();
            $this->endpointToken = $this->providers[0]->getEndpointToken();
            $this->endpointUserInfo = $this->providers[0]->getEndpointUserInfo();
            $this->endpointRevoke = $this->providers[0]->getEndpointRevoke();
            $this->endpointLogout = $this->providers[0]->getEndpointLogout();
            $this->usersStoragePids = $this->providers[0]->getUsersStoragePids();
            $this->usersDefaultGroup = $this->providers[0]->getUsersDefaultGroup();
            $this->redirectUri = $this->providers[0]->getRedirectUri();
            $this->revokeAccessTokenAfterLogin = $this->providers[0]->isRevokeAccessTokenAfterLogin();
            $this->enablePasswordCredentials = $this->providers[0]->isEnablePasswordCredentials();
        } catch (\Exception $e) {
            throw new ExtensionNotConfiguredException(
                'OIDC extension configuration is incomplete. Please, fix it: ' . $e->getMessage(),
                1773075165,
                $e
            );
        }
    }

    public function hasProviderForBackendAuthentication(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isEnableBackendAuthentication()) {
                return true;
            }
        }
        return false;
    }

    public function hasProviderForFrontendAuthentication(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isEnableFrontendAuthentication()) {
                return true;
            }
        }
        return false;
    }
}
