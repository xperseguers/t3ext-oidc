<?php

declare(strict_types=1);

namespace Causal\Oidc;

use Causal\Oidc\Factory\GenericOAuthProviderFactory;
use Causal\Oidc\Factory\OAuthProviderFactoryInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class OidcConfiguration
{
    public const CONFIG_PATH = '/system/oidc.yaml';

    public int $authenticationServicePriority = 82;
    public int $authenticationServiceQuality = 80;
    public string $authenticationUrlRoute = 'oidc/authentication';
    public string $authorizeLanguageParameter = 'language';
    public string $clientKey = '';
    public string $clientScopeSeparator = ' ';
    public string $clientScopes = 'openid';
    public string $clientSecret = '';
    public bool $disableCSRFProtection = false;
    public bool $enableCodeVerifier = false;
    public bool $enableFrontendAuthentication = false;
    public bool $enablePasswordCredentials = false;
    public string $endpointAuthorize = '';
    public string $endpointLogout = '';
    public string $endpointRevoke = '';
    public string $endpointToken = '';
    public string $endpointUserInfo = '';
    public bool $frontendUserMustExistLocally = false;
    /** @var class-string<OAuthProviderFactoryInterface> */
    public string $oauthProviderFactory = GenericOAuthProviderFactory::class;
    public array $providers = [];
    public bool $reEnableFrontendUsers = false;
    public string $redirectUri = '';
    public bool $revokeAccessTokenAfterLogin = false;
    public bool $undeleteFrontendUsers = false;
    public bool $useRequestPathAuthentication = false;
    public string $usersDefaultGroup = '';
    /** @var int[] */
    public array $usersStoragePids = [0];

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct(array $extConfig = [])
    {
        $extConfig = $extConfig ?: $this->getExtensionConfiguration();

        foreach ($extConfig as $property => $value) {
            if (preg_match("/^oidc\w+$/", $property)) {
                $oldProperty = $property;
                $property = lcfirst(substr($oldProperty, 4));
                trigger_error("Using configuration `$oldProperty` is deprecated and is replaced by `$property`, please update your OIDC configuration", E_USER_DEPRECATED);
            }

            if (property_exists($this, $property)) {
                if (is_string($value)) {
                    $value = trim($value);
                }

                switch ($property) {
                    case 'clientScopeSeparator':
                        $this->clientScopeSeparator = $value === '' ? ' ' : $value;
                        break;
                    case 'oauthProviderFactory':
                        if ($value && !class_exists($value)) {
                            throw new \UnexpectedValueException(
                                'OIDC extension `oauthProviderFactory` class not found',
                                1773075262
                            );
                        }
                        if ($value) {
                            $this->oauthProviderFactory = $value;
                        }
                        break;
                    case 'usersStoragePids':
                        $this->usersStoragePids = GeneralUtility::intExplode(',', $value, true) ?: [0];
                        break;
                    default:
                        settype($value, gettype($this->$property));
                        $this->$property = $value;
                }
            }
        }
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    protected function getExtensionConfiguration(): array
    {
        if (!$config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc')) {
            throw new ExtensionConfigurationExtensionNotConfiguredException(
                'OIDC extension is not yet configured. Please use '
                . 'the Admin Tools / Settings / Extension Configuration module for this.',
                1763986824
            );
        }

        $yamlConfig = GeneralUtility::makeInstance(YamlFileLoader::class)
            ->load(Environment::getConfigPath() . self::CONFIG_PATH);

        $errors = [];
        foreach ($yamlConfig['providers'] as $name => $provider) {
            if (!array_key_exists('mapping', $provider)
                || (!$this->isValideMappingForTable($provider['mapping'], 'fe_users')
                    && !$this->isValideMappingForTable($provider['mapping'], 'be_users'))
            ) {
                $errors[] = ' - Provider `' . $name . '` has no table mapping defined';
            }
        }

        if ($errors) {
            throw new ExtensionConfigurationExtensionNotConfiguredException(
                'OIDC extension configuration is incomplete. Please, fix it:' . PHP_EOL . implode(PHP_EOL, $errors),
                1773075165
            );
        }

        return $config + $yamlConfig;
    }

    protected function isValideMappingForTable($mapping, $table): bool
    {
        if (array_key_exists($table, $mapping) && is_array($mapping[$table])) {
            foreach ($mapping[$table] as $value) {
                if (!$value) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }
}
