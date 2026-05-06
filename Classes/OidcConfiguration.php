<?php

declare(strict_types=1);

namespace Causal\Oidc;

use Causal\Oidc\Factory\GenericOAuthProviderFactory;
use Causal\Oidc\Factory\OAuthProviderFactoryInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class OidcConfiguration
{
    public bool $enableFrontendAuthentication = false;
    public int $authenticationServicePriority = 82;
    public int $authenticationServiceQuality = 80;
    /** @var int[] */
    public array $usersStoragePids = [0];
    public string $usersDefaultGroup = '';
    public bool $reEnableFrontendUsers = false;
    public bool $undeleteFrontendUsers = false;
    public bool $frontendUserMustExistLocally = false;
    public bool $disableCSRFProtection = false;
    public bool $enableCodeVerifier = false;
    public string $authenticationUrlRoute = 'oidc/authentication';
    public string $authorizeLanguageParameter = 'language';
    public bool $useRequestPathAuthentication = false;
    /** @var class-string<OAuthProviderFactoryInterface> */
    public string $oauthProviderFactory = '';
    public string $oidcClientKey = '';
    public string $oidcClientSecret = '';
    public string $oidcClientScopes = 'openid';
    public string $oidcClientScopeSeparator = ' ';
    public string $oidcRedirectUri = '';
    public string $endpointAuthorize = '';
    public string $endpointToken = '';
    public string $endpointUserInfo = '';
    public string $endpointRevoke = '';
    public string $endpointLogout = '';
    public bool $revokeAccessTokenAfterLogin = false;
    public bool $enablePasswordCredentials = false;
    public string $oidcDiscoveryUrl = '';

    /**
     * OIDC Discovery document mapping:
     * discovery key => property name
     */
    private const DISCOVERY_MAP = [
        'authorization_endpoint' => 'endpointAuthorize',
        'token_endpoint'         => 'endpointToken',
        'userinfo_endpoint'      => 'endpointUserInfo',
        'end_session_endpoint'   => 'endpointLogout',
        'revocation_endpoint'    => 'endpointRevoke',
    ];

    public function __construct(array $extConfig = [])
    {
        $extConfig = $extConfig ?: $this->getExtensionConfiguration();

        $this->enableFrontendAuthentication = (bool)$extConfig['enableFrontendAuthentication'];
        $this->authenticationServicePriority = (int)$extConfig['authenticationServicePriority'];
        $this->authenticationServiceQuality = (int)$extConfig['authenticationServiceQuality'];
        $this->reEnableFrontendUsers = (bool)$extConfig['reEnableFrontendUsers'];
        $this->undeleteFrontendUsers = (bool)$extConfig['undeleteFrontendUsers'];
        $this->frontendUserMustExistLocally = (bool)$extConfig['frontendUserMustExistLocally'];
        $this->disableCSRFProtection = (bool)$extConfig['oidcDisableCSRFProtection'];
        $this->enableCodeVerifier = (bool)$extConfig['enableCodeVerifier'];
        $this->authenticationUrlRoute = $extConfig['authenticationUrlRoute'] ?? $this->authenticationUrlRoute;
        $this->authorizeLanguageParameter = $extConfig['oidcAuthorizeLanguageParameter'];
        $this->useRequestPathAuthentication = (bool)$extConfig['oidcUseRequestPathAuthentication'];
        $this->oauthProviderFactory = $extConfig['oauthProviderFactory'] ?: GenericOAuthProviderFactory::class;
        $this->oidcClientKey = $extConfig['oidcClientKey'];
        $this->oidcClientSecret = $extConfig['oidcClientSecret'];
        $this->oidcClientScopes = $extConfig['oidcClientScopes'];
        $this->oidcClientScopeSeparator = ($extConfig['oidcClientScopeSeparator'] ?? '') === '' ? ' ' : $extConfig['oidcClientScopeSeparator'];
        $this->endpointAuthorize = $extConfig['oidcEndpointAuthorize'];
        $this->endpointToken = $extConfig['oidcEndpointToken'];
        $this->endpointUserInfo = $extConfig['oidcEndpointUserInfo'];
        $this->endpointRevoke = $extConfig['oidcEndpointRevoke'];
        $this->endpointLogout = $extConfig['oidcEndpointLogout'];
        $this->usersStoragePids = GeneralUtility::intExplode(',', (string)$extConfig['usersStoragePid'], true) ?: [0];
        $this->usersDefaultGroup = $extConfig['usersDefaultGroup'];
        $this->oidcRedirectUri = $extConfig['oidcRedirectUri'];
        $this->revokeAccessTokenAfterLogin = (bool)$extConfig['oidcRevokeAccessTokenAfterLogin'];
        $this->enablePasswordCredentials = (bool)($extConfig['enablePasswordCredentials'] ?? $this->enablePasswordCredentials);
        $this->oidcDiscoveryUrl = trim((string)($extConfig['oidcDiscoveryUrl'] ?? ''));

        $this->applyDiscovery();
    }

    /**
     * If oidcDiscoveryUrl is set and any endpoint is empty,
     * fetch the OIDC discovery document and fill in the blanks.
     * Manually configured endpoints always take precedence.
     */
    private function applyDiscovery(): void
    {
        if ($this->oidcDiscoveryUrl === '') {
            return;
        }

        $needsDiscovery = false;
        foreach (self::DISCOVERY_MAP as $property) {
            if ($this->{$property} === '') {
                $needsDiscovery = true;
                break;
            }
        }

        if (!$needsDiscovery) {
            return;
        }

        try {
            $document = $this->fetchDiscoveryDocument($this->oidcDiscoveryUrl);

            foreach (self::DISCOVERY_MAP as $discoveryKey => $property) {
                if ($this->{$property} === '' && !empty($document[$discoveryKey])) {
                    $this->{$property} = $document[$discoveryKey];
                }
            }
        } catch (\Throwable) {
            // Discovery failure is non-fatal; manually configured endpoints
            // (if any) remain untouched.
        }
    }

    /**
     * Fetch and parse an OIDC discovery document.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function fetchDiscoveryDocument(string $discoveryUrl): array
    {
        $discoveryUrl = $this->normalizeDiscoveryUrl($discoveryUrl);

        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $response = $requestFactory->request(
            $discoveryUrl,
            'GET',
            ['headers' => ['Accept' => 'application/json']]
        );

        $data = json_decode((string)$response->getBody(), true);

        if (!is_array($data) || empty($data['authorization_endpoint'])) {
            throw new \RuntimeException(
                'Invalid OIDC Discovery response from ' . $discoveryUrl,
                1744200001
            );
        }

        return $data;
    }

    /**
     * Normalize discovery URL:
     * - Bare domain → prepend https://
     * - Missing /.well-known/ path → append it
     */
    private function normalizeDiscoveryUrl(string $url): string
    {
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        if (!str_contains($url, '.well-known')) {
            $url = rtrim($url, '/') . '/.well-known/openid-configuration';
        }

        return $url;
    }

    protected function getExtensionConfiguration(): array
    {
        $config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc');
        if ($config) {
            return $config;
        }
        throw new \UnexpectedValueException('OIDC extension configuration not found', 1763986824);
    }
}
