<?php

declare(strict_types=1);

namespace Causal\Oidc;

use Causal\Oidc\Exception\ExtensionNotConfiguredException;
use Causal\Oidc\Exception\ProviderConfigurationException;
use Causal\Oidc\Model\Provider;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @phpstan-import-type ProviderConfig from Provider
 * @phpstan-type YamlConfig array{authenticationServicePriority: int, authenticationServiceQuality: int, authenticationUrlRoute: string, providers: array<string, ProviderConfig>}
 */
final class OidcConfiguration
{
    public const CONFIG_PATH = '/system/oidc.yaml';

    public int $authenticationServicePriority = 82;
    public int $authenticationServiceQuality = 80;
    public string $authenticationUrlRoute = 'oidc/authentication';

    public string $authorizeLanguageParameter;
    public bool $backendUserMustExistLocally;
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
    public bool $reEnableBackendUsers;
    public bool $reEnableFrontendUsers;
    public string $redirectUri;
    public bool $revokeAccessTokenAfterLogin;
    public bool $undeleteBackendUsers;
    public bool $undeleteFrontendUsers;
    public bool $useRequestPathAuthentication;
    public string $usersDefaultGroup;
    public array $usersStoragePids;
    /** @var array<Provider> */
    private array $providers = [];

    /**
     * @param ?YamlConfig $yamlConfig
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionNotConfiguredException
     * @throws ProviderConfigurationException
     */
    public function __construct(?array $yamlConfig = null)
    {
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
                $this->providers[$name] = new Provider($name, $provider);
            }

            // We only support one provider for now
            $provider = current($this->providers);
            $this->enableBackendAuthentication = $provider->isEnableBackendAuthentication();
            $this->enableFrontendAuthentication = $provider->isEnableFrontendAuthentication();
            $this->reEnableBackendUsers = $provider->isReEnableBackendUsers();
            $this->reEnableFrontendUsers = $provider->isReEnableFrontendUsers();
            $this->undeleteBackendUsers = $provider->isUndeleteBackendUsers();
            $this->undeleteFrontendUsers = $provider->isUndeleteFrontendUsers();
            $this->backendUserMustExistLocally = $provider->isBackendUserMustExistLocally();
            $this->frontendUserMustExistLocally = $provider->isFrontendUserMustExistLocally();
            $this->disableCSRFProtection = $provider->isDisableCSRFProtection();
            $this->enableCodeVerifier = $provider->isEnableCodeVerifier();
            $this->authorizeLanguageParameter = $provider->getAuthorizeLanguageParameter();
            $this->useRequestPathAuthentication = $provider->isUseRequestPathAuthentication();
            $this->oauthProviderFactory = $provider->getOauthProviderFactory();
            $this->clientKey = $provider->getClientKey();
            $this->clientSecret = $provider->getClientSecret();
            $this->clientScopes = $provider->getClientScopes();
            $this->clientScopeSeparator = $provider->getClientScopeSeparator();
            $this->endpointAuthorize = $provider->getEndpointAuthorize();
            $this->endpointToken = $provider->getEndpointToken();
            $this->endpointUserInfo = $provider->getEndpointUserInfo();
            $this->endpointRevoke = $provider->getEndpointRevoke();
            $this->endpointLogout = $provider->getEndpointLogout();
            $this->usersStoragePids = $provider->getUsersStoragePids();
            $this->usersDefaultGroup = $provider->getUsersDefaultGroup();
            $this->redirectUri = $provider->getRedirectUri();
            $this->revokeAccessTokenAfterLogin = $provider->isRevokeAccessTokenAfterLogin();
            $this->enablePasswordCredentials = $provider->isEnablePasswordCredentials();
        } catch (\Exception $e) {
            throw new ExtensionNotConfiguredException(
                'OIDC extension configuration is incomplete. Please, fix it: ' . $e->getMessage(),
                1773075165,
                $e
            );
        }
    }

    public function getProviders(): array
    {
        return $this->providers;
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
