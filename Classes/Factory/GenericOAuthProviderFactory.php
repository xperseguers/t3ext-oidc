<?php

declare(strict_types=1);

/**
 * This file is part of the "oidc" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Causal\Oidc\Factory;

use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Provider\GenericOpenIdDiscoveryProvider;
use Causal\Oidc\Provider\GenericOpenIdProvider;
use League\OAuth2\Client\Provider\AbstractProvider;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class GenericOAuthProviderFactory implements OAuthProviderFactoryInterface
{
    public function __construct(
        private GuzzleClientFactory $clientFactory,
        private RequestFactory $requestFactory,
    ) {}

    public function create(OidcConfiguration $settings): AbstractProvider
    {
        $collaborators = [
            'httpClient' => $this->clientFactory->getClient(),
            'requestFactory' => $this->requestFactory,
        ];

        $options = [
            'clientId' => $settings->oidcClientKey,
            'clientSecret' => $settings->oidcClientSecret,
            'redirectUri' => $settings->oidcRedirectUri,
            'urlAuthorize' => $settings->endpointAuthorize,
            'urlAccessToken' => $settings->endpointToken,
            'urlResourceOwnerDetails' => $settings->endpointUserInfo,
            'responseResourceOwnerId' => 'sub',
            'accessTokenResourceOwnerId' => 'sub',
            'scopes' => GeneralUtility::trimExplode(',', $settings->oidcClientScopes, true),
            'scopeSeparator' => $settings->oidcClientScopeSeparator,
        ];

        if ($settings->oidcDiscoveryUrl !== '') {
            $options['discoveryUrl'] = $settings->oidcDiscoveryUrl;
            $provider = new GenericOpenIdDiscoveryProvider($options, $collaborators);
            $this->applyDiscoveryToSettings($provider, $settings);
            return $provider;
        }

        return new GenericOpenIdProvider($options, $collaborators);
    }

    /**
     * Fill remaining config endpoints (logout, revoke) from the
     * provider's discovery document. League providers only handle
     * authorize, token, and userinfo URLs — logout and revoke are
     * TYPO3-specific and stored in OidcConfiguration.
     */
    private function applyDiscoveryToSettings(
        GenericOpenIdDiscoveryProvider $provider,
        OidcConfiguration $settings
    ): void {
        $doc = $provider->getDiscoveryDocument();
        if ($doc === []) {
            return;
        }

        $extraEndpoints = [
            'end_session_endpoint' => 'endpointLogout',
            'revocation_endpoint' => 'endpointRevoke',
        ];

        foreach ($extraEndpoints as $discoveryKey => $configProperty) {
            if ($settings->{$configProperty} === '' && !empty($doc[$discoveryKey])) {
                $settings->{$configProperty} = $doc[$discoveryKey];
            }
        }
    }
}
