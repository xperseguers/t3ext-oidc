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
    /** @var array<Provider> */
    public array $providers = [];

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
            }
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
