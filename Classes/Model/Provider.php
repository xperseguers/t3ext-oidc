<?php

declare(strict_types=1);

namespace Causal\Oidc\Model;

use Causal\Oidc\Exception\ProviderConfigurationException;
use Causal\Oidc\Factory\GenericOAuthProviderFactory;
use Causal\Oidc\Factory\OAuthProviderFactoryInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @phpstan-type MappingConfig array{be_users?: array<string, int|string>, fe_users?: array<string, int|string>}
 * @phpstan-type ProviderConfig array{
 *     authorizeLanguageParameter: string,
 *     backendUserMustExistLocally: int,
 *     clientKey: string,
 *     clientScopeSeparator: string,
 *     clientScopes: string,
 *     clientSecret: string,
 *     disableCSRFProtection: int,
 *     enableBackendAuthentication: int,
 *     enableCodeVerifier: int,
 *     enableFrontendAuthentication: int,
 *     enablePasswordCredentials: int,
 *     endpointAuthorize: string,
 *     endpointLogout: string,
 *     endpointRevoke: string,
 *     endpointToken: string,
 *     endpointUserInfo: string,
 *     frontendUserMustExistLocally: int,
 *     mapping: MappingConfig,
 *     name: string,
 *     oauthProviderFactory: class-string<OAuthProviderFactoryInterface>,
 *     reEnableBackendUsers: int,
 *     reEnableFrontendUsers: int,
 *     redirectUri: string,
 *     revokeAccessTokenAfterLogin: int,
 *     undeleteBackendUsers: int,
 *     undeleteFrontendUsers: int,
 *     useRequestPathAuthentication: int,
 *     usersDefaultGroup: string,
 *     usersStoragePids: array
 * }
 */
class Provider
{
    private string $administratorRole = '';
    private string $authorizeLanguageParameter = 'language';
    private bool $backendUserMustExistLocally = false;
    private string $clientKey = '';
    private string $clientScopeSeparator = ' ';
    private string $clientScopes = 'openid';
    private string $clientSecret = '';
    private bool $disableCSRFProtection = false;
    private bool $enableBackendAuthentication = false;
    private bool $enableCodeVerifier = false;
    private bool $enableFrontendAuthentication = false;
    private bool $enablePasswordCredentials = false;
    private string $endpointAuthorize = '';
    private string $endpointLogout = '';
    private string $endpointRevoke = '';
    private string $endpointToken = '';
    private string $endpointUserInfo = '';
    private bool $frontendUserMustExistLocally = false;
    private string $maintainerRole = '';
    /** @var MappingConfig */
    private array $mapping = [];
    private string $name;
    /** @var class-string<OAuthProviderFactoryInterface> */
    private string $oauthProviderFactory = GenericOAuthProviderFactory::class;
    private bool $reEnableBackendUsers = false;
    private bool $reEnableFrontendUsers = false;
    private string $redirectUri = '';
    private bool $revokeAccessTokenAfterLogin = false;
    private bool $undeleteBackendUsers = false;
    private bool $undeleteFrontendUsers = false;
    private bool $useRequestPathAuthentication = false;
    private string $usersDefaultGroup = '';
    /** @var int[] */
    private array $usersStoragePids = [0];

    /**
     * @param string $name
     * @param ?ProviderConfig $config
     * @throws ProviderConfigurationException
     */
    public function __construct(string $name, ?array $config)
    {
        $this->name = $name;
        foreach ($config ?? [] as $property => $value) {
            if (!property_exists($this, $property)) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            switch ($property) {
                case 'clientScopeSeparator':
                    $this->clientScopeSeparator = $value === '' ? ' ' : $value;
                    break;
                case 'mapping':
                    if (!is_array($value)) {
                        continue 2;
                    }
                    $this->mapping = $value;
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

        $this->isProperlyConfigured();
    }

    /**
     * @throws ProviderConfigurationException
     */
    protected function isProperlyConfigured(): bool
    {
        $errors = [];
        if (!$this->isEnableBackendAuthentication() && !$this->isEnableFrontendAuthentication()) {
            $errors[] = ' - is disabled for both backend and frontend authentication;';
        }

        if (empty($this->mapping['be_users']) && $this->isEnableBackendAuthentication()) {
            $errors[] = ' - is missing table mapping for be_users;';
        }

        if (empty($this->mapping['fe_users']) && $this->isEnableFrontendAuthentication()) {
            $errors[] = ' - is missing table mapping for fe_users;';
        }

        if (empty($this->clientKey) || empty($this->clientSecret) || empty($this->endpointAuthorize) || empty($this->endpointToken)) {
            $errors[] = ' - is missing endpoint configurations;';
        }

        if (!empty($errors)) {
            throw new ProviderConfigurationException('Provider `' . $this->name . '`: ' . PHP_EOL . implode(PHP_EOL, $errors));
        }

        return true;
    }

    public function isEnableBackendAuthentication(): bool
    {
        return $this->enableBackendAuthentication;
    }

    public function isEnableFrontendAuthentication(): bool
    {
        return $this->enableFrontendAuthentication;
    }

    public function getAdministratorRole(): string
    {
        return $this->administratorRole;
    }

    public function getAuthorizeLanguageParameter(): string
    {
        return $this->authorizeLanguageParameter;
    }

    public function getClientKey(): string
    {
        return $this->clientKey;
    }

    public function getClientScopeSeparator(): string
    {
        return $this->clientScopeSeparator;
    }

    public function getClientScopes(): string
    {
        return $this->clientScopes;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getEndpointAuthorize(): string
    {
        return $this->endpointAuthorize;
    }

    public function getEndpointLogout(): string
    {
        return $this->endpointLogout;
    }

    public function getEndpointRevoke(): string
    {
        return $this->endpointRevoke;
    }

    public function getEndpointToken(): string
    {
        return $this->endpointToken;
    }

    public function getEndpointUserInfo(): string
    {
        return $this->endpointUserInfo;
    }

    public function getMaintainerRole(): string
    {
        return $this->maintainerRole;
    }

    /**
     * @return MappingConfig
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOauthProviderFactory(): string
    {
        return $this->oauthProviderFactory;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function getUsersDefaultGroup(): string
    {
        return $this->usersDefaultGroup;
    }

    public function getUsersStoragePids(): array
    {
        return $this->usersStoragePids;
    }

    public function isBackendUserMustExistLocally(): bool
    {
        return $this->backendUserMustExistLocally;
    }

    public function isDisableCSRFProtection(): bool
    {
        return $this->disableCSRFProtection;
    }

    public function isEnableCodeVerifier(): bool
    {
        return $this->enableCodeVerifier;
    }

    public function isEnablePasswordCredentials(): bool
    {
        return $this->enablePasswordCredentials;
    }

    public function isFrontendUserMustExistLocally(): bool
    {
        return $this->frontendUserMustExistLocally;
    }

    public function isReEnableBackendUsers(): bool
    {
        return $this->reEnableBackendUsers;
    }

    public function isReEnableFrontendUsers(): bool
    {
        return $this->reEnableFrontendUsers;
    }

    public function isRevokeAccessTokenAfterLogin(): bool
    {
        return $this->revokeAccessTokenAfterLogin;
    }

    public function isUndeleteBackendUsers(): bool
    {
        return $this->undeleteBackendUsers;
    }

    public function isUndeleteFrontendUsers(): bool
    {
        return $this->undeleteFrontendUsers;
    }

    public function isUseRequestPathAuthentication(): bool
    {
        return $this->useRequestPathAuthentication;
    }
}
